<?php
namespace Home\Behaviors;
/**
 * Created by PhpStorm.
 * User: rojer
 * Date: 16/3/30
 * Time: 下午2:01
 */

class AuthBehavior extends \Think\Behavior
{

    /**
     * 执行行为 run方法是Behavior唯一的接口
     * @access public
     * @param mixed $params 行为参数
     * @return void
     */
    public function run(&$params)
    {
        $auth = new \Org\Util\Auth();
        $params->setAuth($auth);
        $auth->setTargetModel($params->getTargetModel());
        $auth_result = $auth->checkPermission(CONTROLLER_NAME.'::'.ACTION_NAME);
        if(!$auth_result){
            $params->error($auth->getErroeMsg());
        }
    }
}