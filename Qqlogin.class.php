<?php
/**
*此示例是基于thinkphp3.2的代码，不过对于懂php的新手来说也是能看懂的
*/

/*------- 用户表结构--------*/
CREATE TABLE `hongxin_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `wx_openid` varchar(255) NOT NULL,
  `qq_openid` varchar(255) NOT NULL,
  `reg_time` varchar(30) NOT NULL COMMENT '//注册时间',
  `reg_ip` varchar(30) NOT NULL COMMENT '//注册IP',
  `last_login_ip` varchar(30) NOT NULL COMMENT '//最后登陆IP',
  `last_login_time` varchar(30) NOT NULL COMMENT '//最后登陆时间',
  `user_status` char(1) NOT NULL DEFAULT '0' COMMENT '//用户状态1正常0锁定',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=utf8
/*------- 用户表结构--------*/
/*------- 用户信息表结构---------*/
CREATE TABLE `hongxin_user_info` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) NOT NULL DEFAULT '0',
  `user_nickname` varchar(100) NOT NULL,
  `user_sex` char(1) NOT NULL DEFAULT '1',
  `user_province` varchar(100) NOT NULL,
  `user_city` varchar(100) NOT NULL,
  `user_qq` varchar(20) NOT NULL,
  `user_phone` varchar(20) NOT NULL,
  `user_email` varchar(200) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8
/*------- 用户信息表结构---------*/


namespace Home\Controller;

class PublicController extends CommonController{
	protected $qq_appid				= "";
	protected $qq_appkey			= "";
	protected $qq_redirect_uri		= "";

	public function _initialize()
	{
		parent::_initialize();

		$this->qq_appid				= "您申请的appid";
		$this->qq_appkey			= "您申请的appkey";
		$this->qq_redirect_uri		= urlencode("您的域名/Public/checkQqCode");//回调地址
	}
	//登陆入口
	public function authLogin(){
		$type = I('get.type');
		if($type == 'qq'){
			$_SESSION['state'] = md5(uniqid(rand(), TRUE));
			$jump_login_url = "https://graph.qq.com/oauth2.0/authorize?response_type=code&client_id="
					. $this->qq_appid . "&redirect_uri=" . $this->qq_redirect_uri
					. "&state=" . $_SESSION['state']
					. "&scope=get_user_info";
			header("Location:$jump_login_url");
			exit();
		}
		}else{
			echo '非法操作';exit();
		}
	}
	/*QQ登陆验证开始*/
	public function checkQqCode(){
		$code	= I("get.code");
		$access_token = $this->getQqTokenInfo($code);
		$qq_open_id = $this->getQqOpenId($access_token);
		/*验证用户是否绑定过QQ*/
		$map['qq_openid']	= $qq_open_id;
		$qq_user_info = M("User")->where($map)->find();
		if($qq_user_info){
			$up_user['id']				= $qq_user_info['id'];
			$up_user['last_login_ip']	= get_client_ip(0);
			$up_user['last_login_time']	= time();
			M("User")->save($up_user);
			session("mx_user_id",$qq_user_info['id']);
			header("Location:".U("Order/index"));
			exit();
		}else{
			/*添加用户信息　保存QQ信息*/
			$user_data['wx_openid']		= '';
			$user_data['qq_openid']		= $qq_open_id;
			$user_data['reg_time']		= time();
			$user_data['reg_ip']		= get_client_ip(0);
			$user_data['last_login_ip']	= get_client_ip(0);
			$user_data['last_login_time']	= time();
			$user_data['user_status']		= 1;
			if($insert_userid = M("User")->add($user_data)){
				/*读取QQ用户信息　更新数据库*/
				$qq_userinfo = $this->getQqUserInfo($access_token,$qq_open_id);
				$userinfo['user_id']		= $insert_userid;
				$userinfo['user_nickname']	= $qq_userinfo['nickname']?$qq_userinfo['nickname']:'';
				$userinfo['user_sex']		= $qq_userinfo['gender']=='女'?'2':'1';
				$userinfo['user_province']	= $qq_userinfo['province']?$qq_userinfo['province']:'';
				$userinfo['user_city']		= $qq_userinfo['city']?$qq_userinfo['city']:'';
				M("UserInfo")->add($userinfo);
				session("mx_user_id",$insert_userid);
				header("Location:".U("Order/index"));
			}else{
				_alert_location('QQ登陆失败，请重试',U("Public/login"));
			}
		}

	}
	/*QQ获取token*/
	public function getQqTokenInfo($code){
		$qq_get_access_token_url = "https://graph.qq.com/oauth2.0/token?grant_type=authorization_code&"
				. "client_id=" . $this->qq_appid. "&redirect_uri=" . $this->qq_redirect_uri
				. "&client_secret=" . $this->qq_appkey. "&code=" . $code;
		$qq_access_token_con = curl_get_contents($qq_get_access_token_url);
		if (strpos($qq_access_token_con, "callback") !== false)
		{
			$lpos = strpos($qq_access_token_con, "(");
			$rpos = strrpos($qq_access_token_con, ")");
			$qq_access_token_con  = substr($qq_access_token_con, $lpos + 1, $rpos - $lpos -1);
			$msg = json_decode($qq_access_token_con);
			if (isset($msg->error))
			{
				echo "<h3>code_error:</h3>" . $msg->error;
				echo "<h3>code_msg  :</h3>" . $msg->error_description;
				exit();
			}
		}
		$params = array();
		parse_str($qq_access_token_con, $params);
		return $params["access_token"];

	}
	/*QQ获取用户openid*/
	public function getQqOpenId($access_token){
		$graph_url = "https://graph.qq.com/oauth2.0/me?access_token="
				. $access_token;
		$str  = curl_get_contents($graph_url);
		if (strpos($str, "callback") !== false)
		{
			$lpos = strpos($str, "(");
			$rpos = strrpos($str, ")");
			$str  = substr($str, $lpos + 1, $rpos - $lpos -1);
		}
		$user = json_decode($str);
		if (isset($user->error))
		{
			echo "<h3>openid_error:</h3>" . $user->error;
			echo "<h3>openid_msg  :</h3>" . $user->error_description;
			exit;
		}
		return $user->openid;
	}

	/*QQ获取个人用户信息*/
	public function getQqUserInfo($access_token,$qq_open_id){
		$get_user_info = "https://graph.qq.com/user/get_user_info?"
				. "access_token=" . $access_token
				. "&oauth_consumer_key=" . $this->qq_appid
				. "&openid=" . $qq_open_id
				. "&format=json";

		$info = curl_get_contents($get_user_info);
		$arr = json_decode($info, true);
		return $arr;
	}
}
?>