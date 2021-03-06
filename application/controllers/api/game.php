<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
    require_once APPPATH.'libraries/common/GlobalFunctions.php';
    require APPPATH.'/libraries/REST_Controller.php';

    class Game extends REST_Controller
    {
        public function __construct(){
            parent::__construct();
            $this->load->library('user_agent');
            $this->load->model('follower/follower_model');
            $this->load->model('game/game_model');
            $this->load->model('game/gameaward_model');
        }

        public function index_get(){
            $this->response(array('status'=> 'success', 'content' => 'hello world'));
        }

        public function login_post(){
            // 服务号openid
            $unionid = $this->post('unionid');
            // 订阅号openid
            $username = $this->post('uid');
            $nickname = $this->post('nickname');
            $headimgurl = $this->post('headimgurl');

            if (!$username){
                $data = "no openid";
                $this->response(array('status'=> 'failed', 'content' => $data));
                return;
            }

            $follower = $this->follower_model->get_follower($username);
            if ($follower){
                $array = array(
                    'follower_unionid' => $unionid,
                    'follower_username' => $username,
                    'follower_nickname' => $nickname,
                    'follower_headimgurl' => $headimgurl);
                $this->follower_model->update_follower($array);
                $data = "更新成功";
                $this->response(array('status'=> 'success', 'content' => $data));
                return;
            }else{
                $subscribe_date = getCurrentTime();
                $subscribe_timestamp = getTime();
                $array = array(
                    'follower_unionid' => $unionid,
                    'follower_username' => $username,
                    'follower_subscribe_date' => $subscribe_date,
                    'follower_subscribe_timestamp' => $subscribe_timestamp,
                    'follower_nickname' => $nickname,
                    'follower_headimgurl' => $headimgurl);
                $this->follower_model->set_follower($array);
                $data = "激活成功";
                $this->response(array('status'=> 'success', 'content' => $data));
                return;
            }
        }

        public function activate_post(){
            $follower_username = $this->post('uid');
            $follower_nickname = $this->post('name');
            $follower_tel = $this->post('tel');

            if (!$follower_nickname){
                $data = "请输入姓名";
                $this->response(array('status'=> 'failed', 'content' => $data));
            }

            if (!$follower_tel){
                $data = "请输入手机号码";
                $this->response(array('status'=> 'failed', 'content' => $data));
            }

            if (!(preg_match("/^1[1-9]{1}[0-9]{9}$/", $follower_tel))){
                $data = "请输入正确的手机号码";
                $this->response(array('status'=> 'failed', 'content' => $data));
            }

            $follower = $this->follower_model->get_follower($follower_username);
            if ($follower){
                $array = array(
                    'follower_username' => $follower_username,
                    'follower_nickname' => $follower_nickname,
                    'follower_tel' => $follower_tel);
                $this->follower_model->update_follower($array);
                $data = "激活成功";
                $this->response(array('status'=> 'success', 'content' => $data));
            }else{
                $data = "请关注公共账号后重新激活";
                $this->response(array('status'=> 'failed', 'content' => $data));
            }
        }

        public function log_post(){
            $username = $this->post('uid');
            $game_id = '3';
            $score_type = 'log';
            $score_value = 0;

            $from_username = $this->post('fid');
            // $game_time = $this->post('gametime');

            $login_date = getCurrentTime();
            $login_ip = getIp();
            $login_ua = $this->agent->agent_string();

            // 是否超过兑奖时间
            $expired_string = '2015-1-1 08:00:00';
            $expired_time = strtotime($expired_string);
            $expired_date =  date('Y-m-d H:i:s', $expired_time);
            if($login_date>$expired_date){
               $this->response(array('status'=> 'fail', 'content' => '超过活动截止日期'));
            }

            // 验证是否是粉丝
            // $follower = $this->follower_model->get_follower($username);
            // if ($follower){

            // 是否是黑名单粉丝
            $blacklist = $this->game_model->get_blacklist();
            if (in_array($username, $blacklist)){
                $this->response(array('status'=> 'fail', 'content' => '违规用户'));
            }
            // 分数是否异常
            // if ($score_value > 500){
            //     $this->response(array('status'=> 'fail', 'content' => '游戏积分异常'));
            // }

            if ($username){
                // 游戏日志表
                $array = array(
                    'username' => $username,
                    'game_id' => $game_id,
                    'score_type' => $score_type,
                    'login_date' => $login_date,
                    'login_ip' => $login_ip,
                    'login_ua' => $login_ua,
                    'from_username' => $from_username);
                $this->game_model->set_gamelog($array);

                // 游戏积分表
                $this->game_model->update_highscore($username, $game_id, $score_value, $login_date);

                // 邀请积分表
                if($username){
                    // 自己邀请的人数
                    $invitecount = $this->game_model->get_invitecount($username, $game_id);
                    // 邀请积分
                    $invitescore = $invitecount * 1;
                    $this->game_model->update_invitescore($username, $game_id, $invitescore, $login_date);
                }

                if($from_username){
                    // Invitor的邀请人数
                    $invitecount = $this->game_model->get_invitecount($from_username, $game_id);
                    // 邀请积分
                    $invitescore = $invitecount * 1;
                    $this->game_model->update_invitescore($from_username, $game_id, $invitescore, $login_date);
                }

                $data = "提交成功";
                $this->response(array('status'=> 'success', 'content' => $data));
            }
        }

        public function score_get(){
            if (!$this->get('uid')){
                $this->response(NULL, 400);
            }else{
                $username = $this->get('uid');
            }

            if (!$this->get('game_id')){
                $game_id = '2';
            }else{
                $game_id = $this->get('game_id');
            }

            $follower = $this->follower_model->get_follower($username);
            $nickname = $follower['follower_nickname'];
            $tel = $follower['follower_tel'];
            $ranking = 0;
            $status = '';

            $rank = $this->game_model->get_rank($game_id);
            for ($i=0; $i<count($rank); $i++){
                if ($rank[$i]['username'] == $username){
                    $ranking = $i+1;
                    break;
                }
            }

            $highscore = $this->game_model->get_highscore($username, $game_id);
            $invitescore = $this->game_model->get_invitescore($username, $game_id);
            $totalscore = $highscore + $invitescore;

            if ($ranking == 1){
                $award = "1000元代金券";
            }else if($totalscore>=90){
                $award = "杰克丹尼1瓶";
            }else if($totalscore>=60 && $totalscore <90){
                $award = "8L大扎或伏特加1瓶";
            }else if($totalscore >=30 && $totalscore <60){
                $award = "3L大扎一个或水烟1壶";
            }else if($totalscore >=10 && $totalscore <30){
                $award = "菜单上任意鸡尾酒1杯";
            }else if($totalscore >=5 && $totalscore <10){
                $award = "青岛1瓶";
            }else{
                $award = "谢谢参与";
            }

            $award_delivered = $this->gameaward_model->get_award_status($username, $game_id);

            // 没有记录，插入一条奖品状态记录，初始状态为未使用
            if (!$award_delivered){
                $status = 'unused';
                $this->gameaward_model->set_award_status($username, $game_id, $award, $status);
            }else{
            // 有记录
                switch ($award_delivered['status']) {
                    case 'used':
                        $status = 'used';
                        break;
                    case 'unused':
                        $status = 'unused';
                        // update award
                        $this->gameaward_model->update_award_status($username, $game_id, $award, $status);
                        break;
                    default:
                        $status = 'unknown';
                        break;
                }
            }

            // 元旦才开奖
            $login_date = getCurrentTime();
            $expired_string = '2015-1-1 00:00:00';
            $expired_time = strtotime($expired_string);
            $expired_date =  date('Y-m-d H:i:s', $expired_time);
            if($login_date>$expired_date){
                $this->response(
                    array(
                        'nickname'=>$nickname,
                        'tel'=>$tel,
                        'ranking'=>$ranking,
                        'highscore'=> $highscore,
                        'invitescore' => $invitescore,
                        'totalscore' => $totalscore,
                        'award' => $award,
                        'awardstatus' => $status),
                    200
                );
            }else{
               $this->response(NULL, 200);
            }
        }

        public function awardstatus_post(){
            if (!$this->post('uid')){
                $this->response(NULL, 400);
                return;
            }else{
                $username = $this->post('uid');
            }

            if (!$this->post('game_id')){
                $game_id = '2';
            }else{
                $game_id = $this->post('game_id');
            }

            $award = $this->post('award');
            $status = $this->post('status');

            $this->gameaward_model->update_award_status($username, $game_id, $award, $status);

            $data = "提交成功";
            $this->response(array('status'=> 'success', 'content' => $data));
        }

        public function rank_get(){
            if(!$this->get('game_id')){
                $game_id = 2;
            }else{
                $game_id = $this->get('game_id');
            }

            $limit = 100;
            $rank = $this->game_model->get_rank($game_id, $limit);

            for ($i=0; $i<count($rank); $i++){
                $result[$i]['rank'] = $i+1;
                $result[$i]['uid'] = $rank[$i]['username'];
                $follower = $this->follower_model->get_follower($rank[$i]['username']);
                if ($follower['follower_nickname']){
                    $result[$i]['name'] = $follower['follower_nickname'];
                }else{
                    $result[$i]['name'] = '匿名';
                }

                if ($follower['follower_tel']){
                    $result[$i]['tel'] = substr($follower['follower_tel'],0,3)."****".substr($follower['follower_tel'],7,4);
                }else{
                    $result[$i]['tel'] = '***********';
                }
                $result[$i]['high_score'] = $rank[$i]['high_score'];
                $result[$i]['invite_score'] = $rank[$i]['invite_score'];
                $result[$i]['score'] = $rank[$i]['score'];
            }

            $this->response($result, 200);
        }

        public function blacklist_get(){
            $blacklist = $this->game_model->get_blacklist();
            $this->response($blacklist, 200);
        }

        public function makewish_post(){
            $username = $this->post('uid');
            $game_id = '3';
            $game_string = $this->post('wish');
            $score_type = 'text';

            $login_date = getCurrentTime();
            $login_ip = getIp();
            $login_ua = $this->agent->agent_string();

            // 是否超过兑奖时间
            $expired_string = '2015-01-02 00:00:00';
            $expired_time = strtotime($expired_string);
            $expired_date =  date('Y-m-d H:i:s', $expired_time);
            if($login_date>$expired_date){
               $this->response(array('status'=> 'fail', 'content' => '超过活动截止日期'));
            }

            if ($username){
                // 游戏日志表
                $array = array(
                    'username' => $username,
                    'game_id' => $game_id,
                    'score_type' => $score_type,
                    'game_string' => $game_string,
                    'login_date' => $login_date,
                    'login_ip' => $login_ip,
                    'login_ua' => $login_ua);
                $this->game_model->set_gamelog($array);

                // 邀请积分表
                if($username){
                    // 自己邀请的人数
                    $invitecount = $this->game_model->get_invitecount($username, $game_id);
                    // 邀请积分
                    $invitescore = $invitecount * 1;
                    $this->game_model->update_invitescore($username, $game_id, $invitescore, $login_date);
                }

                if($from_username){
                    // Invitor的邀请人数
                    $invitecount = $this->game_model->get_invitecount($from_username, $game_id);
                    // 邀请积分
                    $invitescore = $invitecount * 1;
                    $this->game_model->update_invitescore($from_username, $game_id, $invitescore, $login_date);
                }

                $data = "提交成功";
                $this->response(array('status'=> 'success', 'content' => $data));

            }
        }

        public function wish_get(){
            $username = $this->get('uid');
            $game_id = '3';
            $score_type = 'text';

            if ($username){
                $wish = $this->game_model->get_wish($username, $game_id);
                if ($wish){
                    $inviteusers = $this->game_model->get_inviteusers($username, $game_id);
                    if($inviteusers){
                        for ($i=0; $i<count($inviteusers); $i++){
                            $invite[$i]['uid'] = $inviteusers[$i];
                            $follower = $this->follower_model->get_follower($invite[$i]['uid']);
                            $invite[$i]['name'] = $follower['follower_nickname'];
                            $invite[$i]['headimg'] = $follower['follower_headimgurl'];
                        }
                    }
                    $this->response(array('status'=> 'success', 'wish' => $wish, 'invite' => $invite));
                }else{
                    $this->response(array('status'=> 'fail', 'content' => 'no wish'));
                }
            }else{
                $this->response(array('status'=> 'fail', 'content' => 'no username'));
            }
        }

    }
?>