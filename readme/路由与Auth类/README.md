
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

 - 建立用户表

    CREATE TABLE `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `tel` varchar(19) NOT NULL COMMENT '手机号',
      `password` char(32) NOT NULL COMMENT '密码',
      `avatar` varchar(500) DEFAULT NULL COMMENT '头像',
      `nick` varchar(45) DEFAULT NULL COMMENT '昵称',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

 - 建立AccessToken表

    CREATE TABLE `access_token` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `token` char(60) NOT NULL DEFAULT '',
      `uid` int(11) NOT NULL COMMENT '用户id',
      `failuretime` int(11) NOT NULL COMMENT '失效时间 时间戳',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    
 - 建立角色组表
    CREATE TABLE `role_group` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `name` varchar(20) DEFAULT NULL COMMENT '用户组名称',
      `rules` varchar(1000) DEFAULT NULL COMMENT '规则数组',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

 - 建立规则表

    CREATE TABLE `rule` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `action` varchar(20) DEFAULT NULL COMMENT '动作标识 唯一标识',
      `name` int(11) DEFAULT NULL COMMENT '规则名称',
      `condition` varchar(300) DEFAULT NULL COMMENT '条件',
      `type` int(1) NOT NULL DEFAULT '0' COMMENT '
          0 - member
          1 - owner
          2 -  everyone ',
      PRIMARY KEY (`id`),
      UNIQUE KEY `action` (`action`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

 - 建立用户归属角色组表
    CREATE TABLE `role_user_play` (
      `uid` int(11) NOT NULL,
      `rid` int(11) NOT NULL,
      PRIMARY KEY (`uid`,`rid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

建立好以上表，下面我们来建立权限验证的类库扩展
在`ThinkPHP/Libaray/Org/Util`下建立`Auth.class.php`

代码如下

    <?php
    
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
    
        //设置检查模块对象信息
        public function setTargetModel($t,$key,$f = null){
            $k == null || $this->foreignKey = $f;
            $this->targetModel = $t;
            $this->targetKey = $key;
    
        }
        //判断用户是否已经登录
        private function checkLogin(){
            $userModel = M($this->table_users);
            $accessTokenModel = M($this->table_access_token);
            $accesstoken = I('server.HTTP_ACCESS_TOKEN','');
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
    
    
        //判断用户是否具有操作权限
        public function checkPermission($actionname){
            //获取动作的定义信息
            $rule = $ruleModel = M($this->table_rule)->where("action = '%s'",$actionname)->find();
    
            //未定义的动作和允许任何人操作的动作直接通过
            if(!$rule || $rule['type'] == $this::TYPE_EVERYONE)
                return true;
    
            //判断用户登录与非
            if($this->checkLogin())
                return false;
    
            $checkResult = false;
            //获取该用户的用户组并关联出该用户组拥有的全部动作id集合
            $roleUserPlayModel = M($this->table_role_user_play)->where('uid = %d',$this->user['id']);
            $roleUserPlayModel = $roleUserPlayModel->join($this->table_role_group.'AS table_role_group on '.$this->table_rule.'.rid = table_role_group.id','LEFT');
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
    
            //判断是否需要验证用户是否拥有模块
            if($rule['type'] == $this::TYPE_OWNER){
                $checkResult = $this->checkOwner();
            }
            return $checkResult;
        }
    
        //判断操作对象所有权
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
    
        //颁发用户token
        public function awardAccessToken($uid,$days = 180){
            $token = $this->getRandChar();
            $accessTokenModel = M($this->table_access_token);
            $data = array(
                'token'=>$token,
                'uid'=>$uid,
                'failuretime'=>strtotime("+$days day")
            );
            $accessTokenModel->add($data);
            return $token;
        }
    
        //获取错误信息
        public function getErroeMsg(){
            return $this->errormsg;
        }
    
        //生成随机字符串
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
    

>未完待续...