<?php

namespace Payever\Providers\DataProviders;

use IO\Services\TemplateService;
use Payever\Services\FinanceExpress\ExpressWidgetService;
use Plenty\Plugin\Templates\Twig;

class PayeverExpressWidgetProductProvider
{
    /**
     * @param Twig $twig
     * @param TemplateService $templateService
     * @param ExpressWidgetService $feService
     * @param $arg
     *
     * @return string
     */
    public function call(Twig $twig, TemplateService $templateService, ExpressWidgetService $feService, $arg)
    {
        //Product page widget
        if ($templateService->isCurrentTemplate('tpl.item') && !empty($arg) && $feService->isEnabledOnProductPage()) {
            return $twig->render('Payever::ExpressWidget.Widget', [
                'widgetJsUrl' => $feService->getWidgetJsUrl(),
                'widgetAttributes' => $feService->getWidgetAttributesProductPage($arg[0]),
            ]);
        }

        return '';
    }
}
