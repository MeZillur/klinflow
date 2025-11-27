<?php
namespace Modules\POS\Controllers\Api;

use Modules\POS\Controllers\PosBaseController;

class RegisterApiController extends PosBaseController
{
    public function checkout()
    {
        // TODO: parse payload, call PricingService->priceCart(), then SaleService->checkout(), then AccountingService->postSale()
        return $this->jsonSuccess(['sale_id' => null, 'sale_no' => null])->send();
    }
}