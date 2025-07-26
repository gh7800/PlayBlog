<?php

namespace Module\Document\Flow;

use Module\Document\Models\Document;

class DocumentService
{
    function approve(Document $document): Document
    {

        return $document->refresh();
    }
}
