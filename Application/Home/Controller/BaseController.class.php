<?php
/**
 * Created by PhpStorm.
 * User: rojer
 * Date: 16/4/5
 * Time: 下午9:28
 */

namespace Home\Controller;


use Think\Controller;

abstract class BaseController extends Controller
{

    abstract function getTargetModel();

    protected $auth = null;
    public function setAuth($self){
        $this->auth = $self;
    }

    function _initialize(){
        \Think\Hook::listen('auth', $this);
    }

    public function error($data,$status=-1){
        $this->ajaxReturn(array('msg'=>$data,'status'=>$status));
    }
}