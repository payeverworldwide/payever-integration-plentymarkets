<?php

namespace Payever\Providers\DataProviders;

use IO\Services\TemplateService;
use Payever\Services\FinanceExpress\ExpressWidgetService;
use Plenty\Plugin\Templates\Twig;

class PayeverExpressWidgetCartProvider
{
    /**
     * @param Twig $twig
     * @param TemplateService $templateService
     * @param ExpressWidgetService $feService
     *
     * @return string
     */
    public function call(Twig $twig, TemplateService $templateService, ExpressWidgetService $feService)
    {
        //Cart page widget
        if ($templateService->isCurrentTemplate('tpl.basket') && $feService->isEnabledOnCartPage()) {
            return $twig->render('Payever::ExpressWidget.Widget', [
                'widgetJsUrl' => $feService->getWidgetJsUrl(),
                'widgetAttributes' => $feService->getWidgetAttributesCartPage(),
            ]);
        }

        return '';
    }
}
