<?php
namespace User\Controller;
use Think\Controller;

/**
 * @Author: Martin Zhou
 * @Version: 1.0.3
 * @Copyright Tencent Security Response Center (TSRC)
 * @Project  https://security.tencent.com/index.php/xsrc
*/

class PostController extends BaseController
{

    public function index($key="")
    {
        
        if($key == ""){
            $model = D('PostView'); 
        }else{
            $where['post.title'] = array('like',"%$key%");
            $where['category.title'] = array('like',"%$key%");
            $where['_logic'] = 'or';
            $model = D('PostView') -> where($where); 
        } 
        
        $id = session('userId');
        $count  = $model->where($where)->where('user_id='.$id)->count();
        $Page = new \Extend\Page($count,20);
        $show = $Page->show();
        $post = $model->order('post.id DESC')->where(array('user_id'=>$id)) ->limit($Page->firstRow.','.$Page->listRows) -> order('time desc') -> select();
		$post_num = $model ->where(array('user_id'=>$id))->count();
        $username = session('username');
        $this->assign('username', $username);
        $this->assign('model', $post);
        $this->assign('page',$show);
		$this->assign('post_num',$post_num);
        $this->display();     
    }
	
    public function filter()
    {
        $type = I('get.tid','','intval');
        $id = session('userId');
        $count  = M('post') -> where(array('user_id'=>$id,'type'=>$type)) -> count();
        $Page = new \Extend\Page($count,20);
        $show = $Page->show();
        $post = M('post') ->limit($Page->firstRow.','.$Page->listRows)->where(array('user_id'=>$id,'type'=>$type))->order('post.id DESC')->select();
        $this->assign('model', $post);
        $this->assign('page',$show);
        $this->display();     
    }
	
	public function search()
    {
        $title = I('post.title',0);
        $id = session('userId');
		$map['title'] = array('like','%'.$title.'%');
        $post_result = M('post') -> field('id,time,title,type,rank') -> where(array('user_id'=>$id)) -> where($map) -> order('post.time DESC') -> select();
		if($post_result){
			$rsp_result['status'] = '1';
			$rsp_result['result'] = $post_result;
			$this -> ajaxReturn ($rsp_result,'JSON');
		} else {
			$rsp_result['status'] = '2';
			$this -> ajaxReturn ($rsp_result,'JSON');
		}
    }
	
    public function add()
    {
        //????????????????????????
        if (!IS_POST) {
			$tmodel= M('setting');
		    $title = $tmodel->where('id=1')->select();
			$username = session('username');
			$this->assign('username', $username);
		    $this->assign('title', $title);
        	$this->assign("category",getSortedCategory(M('category')->select()));
            $this->display();
        }
        if (IS_POST) {
            $model = D("Post");
			$poststat = M('poststat');
			$tmodel= M('setting');
			$site_notify_method = $tmodel->where("name='site_notify_method'")->select();
			$site_robot_callback = $tmodel->where("name='site_robot_callback'")->select();	
			$site_notify_users = $tmodel->where("name='site_notify_users'")->select();

            $model->create_time = time();
			$data = I('post.');
			$code = I('post.verify','','strtolower');
			
			$data['user_id'] = session('userId');
			$data['time'] = time();
			
			if(!($this->check_verify($code))){
				$this->error('???????????????',U('post/add'));
			}
            if (!$model->create()) {
                $this->error($model->getError());
                exit();
            } else {
				$result = $model->field('title,cate_id,attachment,content,time,user_id')->add($data);
                if ($result) {
					$stat['type'] = '1';
					$stat['postid'] = $result;
					$stat['content'] = '??????????????????????????????????????????????????????';
					$stat['operator'] = session('username');
					if ($poststat->add($stat)){
						$time = date("Y-m-d h:i:sa");
						$notify_email = C('NOTIFY_EMAIL');
						$con='??????????????????????????????????????????????????? '.$data['title'].'????????????????????????????????????';  
						SendMail($notify_email,'????????????????????????',$con,'????????????????????????');	
						
						if($site_notify_method[0]['value'] === '1'){
							notify_by_wxrobot($site_robot_callback[0]['value'], $con);
							// ?????????????????????
							//notify_by_wxrobot($site_robot_callback[0]['value'], $site_notify_users[0]['value'], '111');
						}			
						
						$this -> redirect('post/index');
					}else{
						$this->error("??????????????????");
					}
                } else {
                    $this->error("??????????????????");
                }
            }
        }
    }
    
	public function edit()
    {
        //????????????????????????????????????
        if (!IS_POST) {
            $tmodel= M('setting');
            $rid = I('get.rid',0,'intval');
            $uid = session('userId');
            $post = M('post')->where(array('user_id'=>$uid,'id'=>$rid))->find();
            if ($post == NULL){
                $this -> error('????????????',U('Post/index'));
            }
            if ($post['type'] != 1){
                $this -> error('?????????????????????????????????',U('Post/index'));
            }
			$title = $tmodel->where('id=1')->select();
		    $this->assign('title', $title);
            $this->assign('content', $post);
        	$this->assign("category",getSortedCategory(M('category')->select()));
            $this->display();
        }
        if (IS_POST) {
            //????????????????????????
            $model = D("Post");
            $poststat = M('poststat');
            $model->time = time();
			$data = I('post.');
            $rid = I('get.rid',0,'intval');
            $uid = session('userId');
            //Fix???????????????????????????????????????????????????;
            $post = $model->where(array('id'=>$rid,'user_id'=>$uid))->find();
            if ($post['type'] != 1){
                $this -> error('?????????????????????????????????',U('Post/index'));
            }
            //??????????????????????????????
            $stat['type'] = '1';
            $stat['postid'] = $rid;
            $stat['content'] = '???????????????????????????????????????????????????';
            $stat['operator'] = session('username');
            if ($model->where(array('id'=>$rid,'user_id'=>$uid))->field('title,user_id,cate_id,content')->save($data)) {
                    if ($poststat->add($stat)){
                        $time = date("Y-m-d h:i:sa");
						$notify_email = C('NOTIFY_EMAIL');
                        $con='??????,??????????????????????????????????????????????????? '.$data['title'].'???????????????????????????????????????';  
                        SendMail($notify_email,'????????????????????????',$con,'????????????????????????');
                        $this->success("????????????", U('post/view?rid=').$rid);
                    }else{
                        $this->error("????????????");
                    }
                } else {
                    $this->error("????????????");
                }
        }
    }
    
	public function view(){
	    $rid = I('get.rid',0,'int');
		$model = D('PostView'); 
		$uid = session('userId');
		
        $post = $model->where(array('user_id'=>$uid,'id'=>$rid))->find();
        if ($post == NULL){
            $this -> error('????????????',U('Post/index'));
        }
		
		$comment = M('Comment')->where(array('comment.post_id'=>$rid))->select();
		$tmodel= M('setting');
		$title = $tmodel->where('id=1')->select();
        $pstat = M('poststat')->where(array('postid'=>$rid))->select();
		$username = session('username');
		$this->assign('username',$username);
		$this->assign('title', $title);
        $this->assign('model', $post);
		$this->assign('comment',$comment);
        $this->assign('pstat',$pstat);
        $this->display();
    }
	
	public function comment()
    {
        if (!IS_POST) {
        	$this->error("????????????");
        }
        if (IS_POST) {
			
			$data = I('post.');
			$code = I('verify','','strtolower');
			$userid = session('userId');
			$data['update_time'] = time();
			$data['user_name'] = session('username');
            //Fix: ??????????????????
            $postid = $data['post_id'];
			
            $post = M('Post')->where(array('id'=>$postid,'user_id'=>$userid))->find();
			
            if ($post == NULL){
                $this -> error('????????????',U('Post/index'));
            }
			
			if(!($this->check_verify($code))){
				$this->error('???????????????',U('post/index'));
			}
			
            if (M("Comment")->field("user_name,post_id,content,update_time")->add($data)) {
                    $this->success("????????????", U('post/index'));
                } else {
                    $this->error("????????????");
                }
        }
    }
	
	public function confirm()
    {
		$rid = I('get.rid',0,'int');
        $userid = session('userId');		
		$data['type'] = 5;
        if (M('Post') -> where(array('id'=>$rid,'user_id'=>$userid)) -> save($data)) {
            $this -> redirect('post/view', array('rid' => $rid));
        } else {
            $this -> redirect('post/view', array('rid' => $rid));
        }
    }	

	public function attachment_upload(){
		$upload = new \Think\Upload();
		$upload->maxSize   =     8388608;
		$upload->exts      =     array('pdf','7z','zip');
		$upload->rootPath  =     './Public/Uploads/';
		$info   =   $upload->uploadOne($_FILES['attachment']);
		if(!$info) {
			$this->error($upload->getError());
		}else{
			$result['code'] = "200";
			$result['savepath'] = $info['savepath'].$info['savename'];
            $this->ajaxReturn($result,'JSON'); 
		}
	}
	
	//?????????
    public function verify(){
		ob_clean();
        $Verify = new \Think\Verify();
        $Verify->codeSet = '123456789abcdefg';
        $Verify->fontSize = 16;
        $Verify->length = 4;
        $Verify->entry();
    }
	
    protected function check_verify($code){
        $verify = new \Think\Verify();
        return $verify->check($code);
    }
	
}
