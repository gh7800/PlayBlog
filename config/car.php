<?php

return [
    // 部长节点自动审批（倒计时自动同意）总开关
    // 暂停期间置 false，恢复时在 .env 置 true 并 config:cache 即可
    'auto_approve_enabled' => env('CAR_AUTO_APPROVE_ENABLED', false),
];
