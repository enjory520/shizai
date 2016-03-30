>**说在前面的话：**Hello，大家好。我是Rojer，从今天开始，我将用这个公众号记录我的开发历程，与大家分享。**仅适用小白食用，大牛请绕道**。


好了，废话不多说。进入今天正题。


最近我在做一款餐厅排号和预约的APP，名字暂时定做“食在”。APP用的是ionic写，后台以及API打算使用thinkphp+bootstrap，毕竟tp是我第一个学的php框架，比较熟悉。O(∩_∩)O~

------------
###下面说一下我规划的API请求的执行流程：

- router：路由，把请求路由到controller
- 权限认证：
	- 登录验证
	- 行为权限判断
	- 模型拥有权限判断 
- 请求被转发到控制器执行
- 返回数据结果

>**模型拥有权限判断 :** 比如店长具有写店铺信息的权限：即“行为权限判断”，但是要判断他写入的对象是不是belong to 他，所以有了这个判断，应该很好理解吧

###计划好上面的流程，所以我们可以想想TP要怎么做呢？


- 路由：Tp本身就有路由啦，直接拿来用，不多说了。
- 请求被转发到控制器执行：这个也是由TP的路由自己有的，所以也没啥好多说的，主要还是控制器中间的逻辑
- 权限验证：this is a big question！既然router可以直接把请求转到控制器下，那么我们要怎么做权限验证呢？难道要在每个控制器里面写一遍吗？当然，其实我们有其他的解决方法：大家不知道有没有看到其实tp也支持apo面向切入的，在tp叫做**Behavior**。

###规划好下面我们开始动手。
>下载thinkphp：[http://www.thinkphp.cn/down.html](http://www.thinkphp.cn/down.html)
>
>thinphp文档地址：[http://www.kancloud.cn/manual/thinkphp/1678](http://www.kancloud.cn/manual/thinkphp/1678)

然后把它放到本地服务器下，我用的是MAMP，window系统建议使用xmapp
>（如不会请自行百度，xmapp已经算傻瓜操作了，下载就能用，还附带mysql和phpmyadmin）

访问
>http://localhost

就可以看到Application目录下面多了Home目录，以后我们主要在这下面进行开发。

>好了，今天就到这里。明天继续。