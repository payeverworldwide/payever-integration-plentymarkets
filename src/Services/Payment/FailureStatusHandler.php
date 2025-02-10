<?php

namespace Payever\Services\Payment;

use Payever\Services\FinanceExpress\ExpressWidgetService;
use Payever\Services\Generator\OrderGenerator;
use Payever\Services\PayeverService;
use Payever\Services\Processor\OrderProcessor;
use Payever\Traits\Logger;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract;
use Plenty\Modules\Item\Variation\Contracts\VariationRepositoryContract;

/**
 * Class FailureStatusHandler
 */
class FailureStatusHandler
{
    use Logger;

    /**
     * @var PayeverService
     */
    private PayeverService $payeverService;

    /**
     * @var OrderProcessor
     */
    private OrderProcessor $orderProcessor;

    /**
     * @var OrderGenerator
     */
    private OrderGenerator $orderGenerator;

    /**
     * @var ItemRepositoryContract
     */
    private ItemRepositoryContract $itemRepository;

    /**
     * @var FrontendSessionStorageFactoryContract
     */
    private FrontendSessionStorageFactoryContract $sessionStorage;

    private VariationRepositoryContract $variationRepositoryContract;

    /**
     * @param PayeverService $payeverService
     * @param OrderProcessor $orderProcessor
     * @param OrderGenerator $orderGenerator
     * @param ItemRepositoryContract $itemRepository
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param VariationRepositoryContract $variationRepositoryContract
     */
    public function __construct(
        PayeverService $payeverService,
        OrderProcessor $orderProcessor,
        OrderGenerator $orderGenerator,
        ItemRepositoryContract $itemRepository,
        FrontendSessionStorageFactoryContract $sessionStorage,
        VariationRepositoryContract $variationRepositoryContract,
    ) {
        $this->payeverService = $payeverService;
        $this->orderProcessor = $orderProcessor;
        $this->orderGenerator = $orderGenerator;
        $this->itemRepository = $itemRepository;
        $this->sessionStorage = $sessionStorage;
        $this->variationRepositoryContract = $variationRepositoryContract;
    }

    /**
     * @param string $paymentId
     *
     * @return string
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getFailureUrl(string $paymentId): string
    {
        $redirect = 'checkout';
        try {
            $payeverPayment = $this->payeverService->handlePayeverPayment($paymentId);

            // Update status only for created order - order id is numeric
            if ($payeverPayment && is_numeric($payeverPayment['reference'])) {
                $this->orderProcessor->updatePlentyOrderStatus($payeverPayment['reference'], $payeverPayment['status']);
            }

            if (
                !empty($payeverPayment['reference'])
                && ExpressWidgetService::isWidgetCartPayment($payeverPayment['reference'])
            ) {
                $this->orderGenerator->prepareBasketItemsByPayeverPayment($payeverPayment);
                $redirect = 'basket';
            }

            if (
                !empty($payeverPayment['reference'])
                && ExpressWidgetService::isWidgetProductPayment($payeverPayment['reference'])
            ) {
                $variation = $this->variationRepositoryContract->findById($payeverPayment['items'][0]['identifier']);
                $lang = $this->sessionStorage->getLocaleSettings()->language;
                $item = $this->itemRepository->show($variation->itemId, [], $lang);

                if ($item && $item->texts->first()) {
                    $redirect = $item->texts->first()->urlPath . '_' . $item->id . '_' . $item->mainVariationId;
                }
            }
        } catch (\Exception $e) {
            $this->log(
                'critical',
                __METHOD__,
                'Payever::debug::failedUrlError',
                'Exception: ' . $e->getMessage(),
                [$e]
            );
        }

        return $redirect;
    }
}
