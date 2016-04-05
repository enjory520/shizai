<?php
namespace Home\Controller;
use Think\Controller;
class IndexController extends BaseController {
    public function index(){
    }

    function getTargetModel()
    {
        return array('test',I('a'),'uid');
    }
}