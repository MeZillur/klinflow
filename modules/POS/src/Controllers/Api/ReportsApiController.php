<?php
namespace Modules\POS\Controllers\Api;
use Modules\POS\Controllers\PosBaseController;

class ReportsApiController extends PosBaseController
{
    public function summary(){ return $this->ok(['summary'=>[]])->send(); }
    public function byProduct(){ return $this->ok(['rows'=>[]])->send(); }
    public function byCashier(){ return $this->ok(['rows'=>[]])->send(); }
    public function byMethod(){ return $this->ok(['rows'=>[]])->send(); }
    public function export(){ return $this->ok(['csv'=>''])->send(); }
}