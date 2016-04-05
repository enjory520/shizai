<?php
/**
 * Created by PhpStorm.
 * User: rojer
 * Date: 16/3/30
 * Time: 下午1:38
 */

namespace Org\Util;


class Auth
{

    private $user = null;
    private $table_users = 'users';
    private $table_access_token = 'access_token';
    private $table_role_group = 'role_group';
    private $table_rule = 'rule';
    private $table_role_user_play = 'role_user_play';
    private $errormsg = '';

    const TYPE_MEMBER = 0;
    const TYPE_OWNER = 1;
    const TYPE_EVERYONE = 2;

    private $targetModel = null;
    private $targetKey = null;
    private $foreignKey = null;

    public function __construct() {
        $this->foreignKey = $this->table_users.'_id';
    }

    /**
     * 设置检查模块对象信息
     * @param $t 对象模块名称
     * @param $key 目标主键
     * @param null $f 外键
     */
    public function setTargetModel($arr){
        $t = $arr[0];
        $key = $arr[1];
        $f = $arr[2];

        $f == null || $this->foreignKey = $f;
        $this->targetModel = $t;
        $this->targetKey = $key;
    }
    /**
     * 判断用户是否已经登录
     *
     * @param $accesstoken
     * @return bool
     */
    private function checkLogin(){
        $userModel = M($this->table_users);
        $accesstoken = I('server.HTTP_ACCESS_TOKEN',null);
        if(!$accesstoken){
            $this->errormsg = '请输入您的accesstoken!';
            return false;
        }
        $accessTokenModel = M($this->table_access_token);
        $tokenInfo = $accessTokenModel->where("'token = '%s'",$accesstoken)->find();
        if(!$tokenInfo){
            $this->errormsg = '认证失败!';
            return false;
        }

        if($tokenInfo['failuretime'] < time()){
            $this->errormsg = '认证已经失效,请重新认证!';
            return false;
        }

        $this->user = $userModel->find($tokenInfo['uid']);
        return true;
    }

    public function refreshToken(){
        $accesstoken = I('server.HTTP_ACCESS_TOKEN',null);
        if(!$accesstoken){
            $this->errormsg = '请输入您的accesstoken!';
            return false;
        }
        $accessTokenModel = M($this->table_access_token);
        $tokenInfo = $accessTokenModel->where("'token = '%s'",$accesstoken)->find();
        if(!$tokenInfo){
            $this->errormsg = '无效请输入您的accesstoken!';
            return false;
        }

        if($tokenInfo['failuretime'] < time()){
            $this->errormsg = '认证已经失效,请重新认证!';
            return false;
        }

        $time = strtotime("+30 day");

        $data = array(
            'failuretime'=> $time
        );
        $accessTokenModel->where("'token = '%s'",$accesstoken)->save($data);
        return $time;
    }


    /**
     * 判断用户是否具有操作权限
     *
     * @param $actionname
     * @return bool
     */
    public function checkPermission($actionname){
        //获取动作的定义信息
        $rule = $ruleModel = M($this->table_rule)->where("action = '%s'",$actionname)->find();

        //未定义的动作和允许任何人操作的动作直接通过
        if(!$rule || $rule['type'] == $this::TYPE_EVERYONE)
            return true;

        //判断用户登录与非
        if(!$this->checkLogin())
            return false;

        $checkResult = false;
        //获取该用户的用户组并关联出该用户组拥有的全部动作id集合
        $roleUserPlayModel = M($this->table_role_user_play)->where('uid = %d',$this->user['id']);
        $roleUserPlayModel = $roleUserPlayModel->join($this->table_role_group.' AS table_role_group on '.$this->table_rule.'.rid = table_role_group.id','LEFT');
        $groups = $roleUserPlayModel->select();
        $permission_ids = array();
        foreach($groups as $group){
            $ids = explode(',',$groups['rules']);
            $permission_ids = array_merge($permission_ids,$ids);
        }
        $permission_ids = array_unique($permission_ids);

        //判断动作是否
        if(in_array($rule['id'],$permission_ids))
            $checkResult = true;

        if (!empty($rule['condition'])) {
            //条件验证
            $user = $this->user;
            $command = preg_replace('/\{(\w*?)\}/', '$user[\'\\1\']', $rule['condition']);
            //dump($command);//debug
            $condition = false;
            @(eval('$condition=(' . $command . ');'));

            if (!$condition) {
                $this->errormsg = "用户 $command ,不符合要求的 ".$rule['condition']." 条件!";
                return false;
            }
        }

        //判断是否需要验证用户是否拥有模块
        if($rule['type'] == $this::TYPE_OWNER){
            $checkResult = $this->checkOwner();
        }
        return $checkResult;
    }

    /**
     * 判断操作对象所有权
     *
     * @param $checkmodel
     * @param string $foreign
     * @return bool
     */
    private function checkOwner(){
        if($this->targetModel == null || $this->targetKey == null)
            return false;
        $result = M($this->targetModel)->find($this->targetKey);
        if($result[$this->foreignKey] == $this->user['id']){
            return true;
        }else{
            $this->errormsg = '您没有权限操作该资源!';
            return false;

        }
    }

    /**
     * 颁发用户token
     *
     * @param $uid
     * @param int $days
     * @param $time
     * @return null|string
     */
    public function awardAccessToken($uid,$days = 30,&$time){
        $token = $this->getRandChar();
        $accessTokenModel = M($this->table_access_token);
        $time = strtotime("+$days day");
        $data = array(
            'token'=>$token,
            'uid'=>$uid,
            'failuretime'=> $time
        );
        $accessTokenModel->add($data);
        return $token;
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function getErroeMsg(){
        return $this->errormsg;
    }

    /**
     * 生成随机字符串
     * @param int $length
     * @return null|string
     */
    private function getRandChar($length = 60){
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol)-1;

        for($i=0;$i<$length;$i++){
            $str.=$strPol[mt_rand(0,$max)];//mt_rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }
        return $str;
    }
}