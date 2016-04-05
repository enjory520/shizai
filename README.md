>**说在前面的话：**Hello，大家好。我是Rojer，从今天开始，我将用公众号`miguphp`以及`这里`记录我的开发历程，与大家分享。**仅适用小白食用，大牛请绕道**。


好了，废话不多说。进入今天正题。


最近我在做一款餐厅排号和预约的APP，名字暂时定做“食在”。APP用的是ionic写，后台以及API打算使用thinkphp+bootstrap，毕竟tp是我第一个学的php框架，比较熟悉。O(∩_∩)O~

------------
###规划

**API请求的执行流程：**
- router：路由，把请求路由到controller
- 权限认证：
	- 登录验证
	- 行为权限判断
	- 模型拥有权限判断 
- 请求被转发到控制器执行
- 返回数据结果

>**模型拥有权限判断 :** 比如店长具有写店铺信息的权限：即“行为权限判断”，但是要判断他写入的对象是不是belong to 他，所以有了这个判断，应该很好理解吧

**计划好上面的流程，所以我们可以想想TP要怎么做呢？**


- 路由：Tp本身就有路由啦，直接拿来用，不多说了。
- 请求被转发到控制器执行：这个也是由TP的路由自己有的，所以也没啥好多说的，主要还是控制器中间的逻辑
- 权限验证：this is a big question！既然router可以直接把请求转到控制器下，那么我们要怎么做权限验证呢？难道要在每个控制器里面写一遍吗？当然，其实我们有其他的解决方法：大家不知道有没有看到其实tp也支持apo面向切入的，在tp叫做**Behavior**。

###关于ThinkPHP基础
>下载thinkphp：[http://www.thinkphp.cn/down.html](http://www.thinkphp.cn/down.html)
>thinphp文档地址：[http://www.kancloud.cn/manual/thinkphp/1678](http://www.kancloud.cn/manual/thinkphp/1678)

然后把它放到本地服务器下，我用的是MAMP，window系统建议使用xmapp
>（如不会请自行百度，xmapp已经算傻瓜操作了，下载就能用，还附带mysql和phpmyadmin）

访问`http://localhost`

>就可以看到Application目录下面多了Home目录，以后我们主要在这下面进行开发。
>然后你需要做的是填写好数据库的信息，与数据建立连接

###建立Router
在配置`Home/Conf/config.php`中开启路由，同时为了将路由单独分离出来，我们在这个文件夹下面单独建一个`router.php`，并在`Home/Conf/config.php`这样写：

    <?php
    return array(
    	//'配置项'=>'配置值'
        'URL_ROUTER_ON'   => true,
        'URL_ROUTE_RULES'=> include_once __DIR__.'/router.php',
    );
之后我们在router.php添加测试路由

    <?php
    return array(
        'news/:id'               => 'Index/index',
    );
访问`http://localhost/news/1`，之后我们发现`无法加载模块:News`的错误提示，那是因为我们没有绑定默认模块，所以必须用`http://localhost/Home/news/1`访问，或者在`index.php`添加`define('BIND_MODULE','Home');`

解决好上面问题，我们就能看主页了。

###设置权限验证类
####建立数据库
 - 建立用户表
```sql
    CREATE TABLE `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `tel` varchar(19) NOT NULL COMMENT '手机号',
      `password` char(32) NOT NULL COMMENT '密码',
      `avatar` varchar(500) DEFAULT NULL COMMENT '头像',
      `nick` varchar(45) DEFAULT NULL COMMENT '昵称',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```
 - 建立AccessToken表
```sql
    CREATE TABLE `access_token` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `token` char(60) NOT NULL DEFAULT '',
      `uid` int(11) NOT NULL COMMENT '用户id',
      `failuretime` int(11) NOT NULL COMMENT '失效时间 时间戳',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```
 - 建立角色组表
```sql
    CREATE TABLE `role_group` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `name` varchar(20) DEFAULT NULL COMMENT '用户组名称',
      `rules` varchar(1000) DEFAULT NULL COMMENT '规则数组',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```
 - 建立规则表
```sql
    CREATE TABLE `rule` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `action` varchar(20) DEFAULT NULL COMMENT '动作标识 唯一标识',
      `name` varchar(30) DEFAULT NULL COMMENT '规则名称',
      `condition` varchar(300) NOT NULL DEFAULT '' COMMENT '条件',
      `type` int(1) NOT NULL DEFAULT '0' COMMENT '\n      0 - member\n      1 - owner\n      2 -  everyone ',
      PRIMARY KEY (`id`),
      UNIQUE KEY `action` (`action`)
    ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
```
 - 建立用户归属角色组表
```sql
    CREATE TABLE `role_user_play` (
      `uid` int(11) NOT NULL,
      `rid` int(11) NOT NULL,
      PRIMARY KEY (`uid`,`rid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```
####编写权限验证类
建立好以上表，下面我们来建立权限验证的类库扩展
在`ThinkPHP/Libaray/Org/Util`下建立`Auth.class.php`

代码如下
```php
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
``` 
###设置验证权限行为

在`Application/Common/Conf`下建立`tags.php`内容如下：

```php
<?php
/**
 * Created by PhpStorm.
 * User: rojer
 * Date: 16/3/30
 * Time: 下午1:59
 */

return array(
    'auth'=>array('Home\\Behaviors\\AuthBehavior'),
);
```
然后我们在Application/Home/Behaviors下建立AuthBehavior.class.php，代码如下：

```php
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
```

之后我们要建立一个抽象类`Application/Home/Controller/BaseController.class.php`继承自`Controller`代码如下：

```php
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
```
最后我们把`Application/Home/Controller/IndexController.class.php`继承自`BaseController`并实现`getTargetModel`方法即可：

```php
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
```
在数据库中，我们插入

```sql
INSERT INTO `rule` (`id`, `action`, `name`, `condition`, `type`)
VALUES
	(1, 'Index::index', '测试', '{nick}=\'1\'', 0);

```
之后访问`http://localhost/news/1`就能看到

```json
{"msg": "请输入您的accesstoken!", "status": -1}
```
的提示。

> 基础Auth部分完毕
Git地址：[https://github.com/enjory520/shizai.git][1]


  [1]: https://github.com/enjory520/shizai.git