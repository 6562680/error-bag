<?php

namespace Gzhegow\ErrorBag;

use Gzhegow\ErrorBag\Struct\ErrorBagItem;


interface ErrorBagFactoryInterface
{
    public function newErrorBagStack() : ErrorBagStackInterface;

    public function newErrorBagPool() : ErrorBagPoolInterface;

    public function newErrorBagItem() : ErrorBagItem;
}
