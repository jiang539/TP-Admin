<?php
// +----------------------------------------------------------------------
// | TP-Admin [ 多功能后台管理系统 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2016 http://www.hhailuo.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: 逍遥·李志亮 <xiaoyao.working@gmail.com>
// +----------------------------------------------------------------------

namespace Logic\Admin;
use Lib\Log;
use Logic\BaseLogic;
use Org\Util\Rbac as RBAC;

/**
 * 登录控制Logic
 */
class LoginLogic extends BaseLogic {
    protected $jumpUrl = '';

    public function checkLogin($username, $password, $verify) {
        $this->jumpUrl = __MODULE__ . C('USER_AUTH_GATEWAY');
        if (empty($username) || empty($password) || empty($verify)) {
            $this->errorCode    = 10001;
            $this->errorMessage = '用户名|密码|验证码都必须！';
            return false;
        }
        if (session('verify') != md5($verify)) {
            $this->errorCode    = 10001;
            $this->errorMessage = '验证码错误！';
            return false;
        }
        // 生成认证条件
        $map = [];
        // 支持使用绑定帐号登录
        $map['account'] = $username;
        $map["status"]  = ['gt', 0];

        $authInfo             = RBAC::authenticate($map);
        $allow_try_error_time = C('ALLOW_TRY_ERROR_TIME', null, 5);
        if ($authInfo['try_time'] >= $allow_try_error_time) {
            $this->errorCode    = 10002;
            $this->errorMessage = '登录失败次数过多，帐号已被禁用，请与管理员联系！';
            return false;
        }
        model('User', 'Admin')->where(['id' => $authInfo['id']])->save(['try_time' => ['exp', '`try_time` + 1']]);
        //使用用户名、密码和状态的方式进行认证
        if (empty($authInfo)) {
            logic('Log', 'Admin')->add($username);
            $this->errorCode    = 10003;
            $this->errorMessage = '帐号不存在或已禁用！';
            return false;
        } else {
            if ($authInfo['password'] != md5($password)) {
                logic('Log', 'Admin')->add($username);
                $this->errorCode    = 10003;
                $this->errorMessage = '密码错误！你还有' . ($allow_try_error_time - 1 - $authInfo['try_time']) . '尝试次机会';
                return false;
            }
            // 保存Session
            session('user_info', $authInfo);
            session(C('USER_AUTH_KEY'), $authInfo['id']);
            session('lastLoginTime', $authInfo['last_login_time']);
            if ($authInfo['role_id'] == 1) {
                session('administrator', true);
            }
            //保存登录信息
            model('User', 'Admin')->where(['id' => $authInfo['id']])->save(['last_login_time' => time(), 'last_login_ip' => get_client_ip(), 'try_time' => 0]);

            //保存日志
            logic('Log', 'Admin')->add($username, 1, $authInfo['id']);
            // 存储访问权限
            RBAC::saveAccessList();
            // 设置默认站点
            $sites        = logic('site', 'Admin')->getAccessibleSites();
            $current_site = current($sites);
            set_siteid($current_site['id']);
            return true;
        }
    }

    public function getJumpUrl() {
        return $this->jumpUrl;
    }

}