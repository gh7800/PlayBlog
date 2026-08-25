<?php

namespace App\Services;

use App\Models\FileContent;
use App\Models\FileModel;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * 纯 PHP 文件文本提取器（2C2G 低配方案，不依赖 LibreOffice / OCR）。
 *
 * 支持范围：
 *  - pdf          → smalot/pdfparser（需 composer require smalot/pdfparser，未装时抛异常记录）
 *  - docx/xlsx/pptx → ZipArchive 解包 + 剥 XML 文本（PHP 原生，零依赖）
 *  - txt/md/csv/json/xml/html/log → 直接读文件
 *  - 其他（wps/doc 老二进制、扫描件、图片等）→ unparseable，不报错只标记
 *
 * 资源保护：提取结果截断到 MAX_TEXT_LENGTH（字符），防止超大文件撑爆内存与索引表。
 */
class FileTextExtractor
{
    /** 单文件提取文本上限（字符数），约等于 20 万字 */
    public const MAX_TEXT_LENGTH = 200000;

    /** 可直接按纯文本读取的扩展名 */
    protected $plainExtensions = ['txt', 'md', 'csv', 'json', 'xml', 'html', 'htm', 'log'];

    /**
     * 为一个已入库的文件提取文本并写入 file_contents 索引表（新增或覆盖）。
     * 任何异常都被吞掉并记录到 status=failed + parse_error，绝不向上抛（不影响上传主流程）。
     */
    public function index(FileModel $file): void
    {
        $status = 'pending';
        $text   = null;
        $error  = null;

        try {
            $absPath = Storage::disk('public')->path($file->file_path);
            $ext     = strtolower(pathinfo($file->title ?: $file->file_path, PATHINFO_EXTENSION));

            if (!is_file($absPath)) {
                throw new \Exception("物理文件不存在: {$absPath}");
            }

            $text = $this->extract($absPath, $ext);
            if ($text === null) {
                $status = 'unparseable'; // 格式不支持，属正常情况
            } else {
                $text   = $this->normalize($text);
                $status = 'parsed';
            }
        } catch (Throwable $e) {
            $status = 'failed';
            $error  = mb_substr($e->getMessage(), 0, 500);
            \Log::error("FileTextExtractor 解析失败 file_id={$file->id}: " . $e->getMessage());
        }

        FileContent::updateOrCreate(
            ['file_id' => $file->id],
            [
                'title'       => $file->title ?? '',
                'text'        => $text ?? '',
                'status'      => $status,
                'parse_error' => $error,
                'char_count'  => $text !== null ? mb_strlen($text) : 0,
            ]
        );
    }

    /**
     * 按扩展名分发提取。返回提取的原始文本；不支持的格式返回 null。
     */
    public function extract(string $absPath, string $ext): ?string
    {
        if ($ext === 'pdf') {
            return $this->fromPdf($absPath);
        }
        if (in_array($ext, ['docx', 'xlsx', 'pptx'], true)) {
            return $this->fromOfficeXml($absPath, $ext);
        }
        if (in_array($ext, $this->plainExtensions, true)) {
            $content = file_get_contents($absPath);
            return $content === false ? null : $content;
        }
        return null;
    }

    /**
     * PDF 文本提取：smalot/pdfparser（纯 PHP）。
     * 注意：只能读"文字型"PDF，扫描件（图片型）提取不到文字，会得到空文本。
     * 兼容：vendor 里的包是手动放置的（私有镜像失效 + 服务器 vendor/lock 分叉，
     * 不能跑 composer），类未被 composer autoloader 注册时，
     * 用包自带的 alt_autoload.php-dist 独立加载器兜底。
     */
    protected function fromPdf(string $absPath): ?string
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            $alt = dirname(__DIR__, 2) . '/vendor/smalot/pdfparser/alt_autoload.php-dist';
            if (is_file($alt)) {
                require_once $alt;
            } else {
                throw new \Exception('未安装 smalot/pdfparser：vendor/smalot/pdfparser 目录不存在');
            }
        }
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($absPath);
        $text   = $pdf->getText();
        return trim($text) === '' ? null : $text;
    }

    /**
     * docx / xlsx / pptx 提取：Office 2007+ 本质是 zip 包，解开对应 XML 剥文本节点。
     * PHP 原生 ZipArchive，零外部依赖，内存中按需读取条目。
     */
    protected function fromOfficeXml(string $absPath, string $ext): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($absPath) !== true) {
            throw new \Exception("无法打开 {$ext} 文件（zip 解包失败）");
        }

        try {
            $text = '';
            switch ($ext) {
                case 'docx':
                    // 正文在 word/document.xml
                    $text = $this->stripXmlText($zip->getFromName('word/document.xml') ?: '');
                    break;

                case 'xlsx':
                    // 单元格字符串统一存在 xl/sharedStrings.xml（数字等直接写在单元格里的读不到，够用）
                    $text = $this->stripXmlText($zip->getFromName('xl/sharedStrings.xml') ?: '');
                    break;

                case 'pptx':
                    // 每页幻灯片是 ppt/slides/slideN.xml，逐个拼接
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $name = $zip->getNameIndex($i);
                        if ($name !== null && preg_match('#^ppt/slides/slide\d+\.xml$#', $name)) {
                            $slide = $this->stripXmlText($zip->getFromName($name) ?: '');
                            if ($slide !== '') {
                                $text .= $slide . "\n";
                            }
                        }
                    }
                    break;
            }
            return trim($text) === '' ? null : $text;
        } finally {
            $zip->close();
        }
    }

    /**
     * 从 Office XML 片段剥出可读文本：标签换空格 + 实体解码。
     * 只做"能全文检索"级别的粗提取，不保留版式（低配方案取舍）。
     */
    protected function stripXmlText(string $xml): string
    {
        if ($xml === '') {
            return '';
        }
        // 段落/换行标签换成换行，其余标签换成空格
        $xml = preg_replace('#</w:p>|</a:p>|<br\s*/?>#', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<[^>]+>/', ' ', $xml) ?? $xml;
        $xml = html_entity_decode($xml, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return trim($xml);
    }

    /**
     * 归一化：压缩空白 + 截断到上限，保证入库文本紧凑可控。
     */
    protected function normalize(string $text): string
    {
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = trim($text);
        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_TEXT_LENGTH);
        }
        return $text;
    }
}
