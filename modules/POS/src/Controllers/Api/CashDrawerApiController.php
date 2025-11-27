<?php
namespace Modules\POS\Controllers\Api;
use Modules\POS\Controllers\PosBaseController;

class CashDrawerApiController extends PosBaseController
{
    public function open(){ return $this->ok(['shift_id'=>null])->send(); }
    public function close(){ return $this->ok(['closed'=>false])->send(); }
    public function list(){ return $this->ok(['shifts'=>[]])->send(); }
    public function show($id){ return $this->ok(['id'=>(int)$id])->send(); }
    public function entry(){ return $this->ok(['entry_id'=>null])->send(); }
}