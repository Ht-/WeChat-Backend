<?php
/*
Plugin Name: 微信
Plugin URI: http://horizononline.cn/
Description: 亲们不要随便乱动这个
Version: @_@
Author: ^_^
Author URI: http://horizononline.cn/
*/

define("WEIXIN_TOKEN", "illumer");

define("WEIXIN_DEFAULT", '');

require_once "simple_html_dom.php";


add_action('pre_get_posts', 'wpjam_wechat_redirect', 4);
function wpjam_wechat_redirect($wp_query)
{
    if(isset($_GET['weixin']) )
	{
        global $wechatObj;
        if(!isset($wechatObj))
		{
            $wechatObj = new wechatCallback();
            $wechatObj->valid();
            exit;
        }
    }
}

class wechatCallback
{
    private $items = '';
    private $articleCount = 0;
    private $keyword = '';

    public function valid()
    {
        if(isset($_GET['debug'])){
            $this->keyword = $_GET['t'];
            $this->responseMsg();
        }

        $echoStr = $_GET["echostr"];

        if($this->checkSignature()){
            echo $echoStr;
            $this->responseMsg();
            
            exit;
        }
    }

    public function responseMsg()
    {
      
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];

      
        if (isset($_GET['debug']) || !empty($postStr))
		{    
            if(!isset($_GET['debug']))
			{
                $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
                $fromUsername = $postObj->FromUserName;
                $toUsername = $postObj->ToUserName;
                $msgType = strtolower(trim($postObj->MsgType));
                if($msgType == 'event')
				{
                    $this->keyword = strtolower(trim($postObj->Event));
                }
				else
				{
                    $this->keyword = strtolower(trim($postObj->Content));
                }
            }

            $time = time();
            $textTpl = "<xml>
                        <ToUserName><![CDATA[".$fromUsername."]]></ToUserName>
                        <FromUserName><![CDATA[".$toUsername."]]></FromUserName>
                        <CreateTime>".$time."</CreateTime>
                        <MsgType><![CDATA[text]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                        <FuncFlag>0</FuncFlag>
                        </xml>";     
            $picTpl = " <xml>
                        <ToUserName><![CDATA[".$fromUsername."]]></ToUserName>
                        <FromUserName><![CDATA[".$toUsername."]]></FromUserName>
                        <CreateTime>".$time."</CreateTime>
                        <MsgType><![CDATA[news]]></MsgType>
                        <Content><![CDATA[]]></Content>
                        <ArticleCount>%d</ArticleCount>
                        <Articles>
                        %s
                        </Articles>
                        <FuncFlag>1</FuncFlag>
                        </xml>";

            $weixin_custom_keywords = apply_filters('weixin_custom_keywords',array());

            if(in_array($this->keyword, $weixin_custom_keywords))
			{
                do_action('weixin_robot',$this->keyword,$textTpl, $picTpl);
            }
			elseif($this->keyword == 'hi' || $this->keyword == '您好'  || $this->keyword == '你好' || $this->keyword == 'subscribe' )
			{
                $weixin_welcome = "欢迎来到illumer! 输入关键词开始检索。";
                $weixin_welcome = apply_filters('weixin_welcome',$weixin_welcome);
                echo sprintf($textTpl, $weixin_welcome);
            }
	elseif(strstr($this->keyword ,'天气') == true )
	{
                 $html= file_get_html('http://www.weather.com.cn/html/weather/101010100.shtml');
                    $content = " ";
				    foreach( $html ->find("div[class=ybnews]") as  $e)
					{
                        $e=strip_tags($e);
                        $content =$content . $e;
                    }
                echo sprintf($textTpl, $content);
            }
			elseif(strstr($this->keyword ,'空气') == true )
			{
				header("Content-Type: text/html; charset=utf-8");  
				$html= file_get_html('http://www.baidu.com/s?wd=%E5%8C%97%E4%BA%AC%E5%B8%82%E7%A9%BA%E6%B0%94%E6%B1%A1%E6%9F%93%E6%8C%87%E6%95%B0');
                $content = "您好，";
				    foreach( $html ->find("div[class=op_pm25_container1]") as  $e)
					{
  						$e=strip_tags($e);
                        $content =$content . $e ."  ";
                    }
                    foreach( $html ->find("div[class=op_pm25_container2]") as  $e)
					{
  						$e=strip_tags($e);
                        $content =$content . $e ."  ";
                    }
                    foreach( $html ->find("div[class=op_pm25_container3]") as  $e)
					{
  						$e=strip_tags($e);
                        $content =$content . $e ."  ";
                    }
                    foreach( $html ->find("div[class=op_pm25_container4]") as  $e)
					{
  						$e=strip_tags($e);
                        $content =$content . $e ."  ";
                    }
                    foreach( $html ->find("div[class=op_pm25_container5]") as  $e)
					{
  						$e=strip_tags($e);
                        $content =$content . $e ."  ";
                    }
                    foreach( $html ->find("div[class=op_pm25_container6]") as  $e)
					{
  						$e=strip_tags($e);
                        $content =$content . $e ."  ";
                    }
                    
                    $contentStr=$content;
                    echo sprintf($textTpl, $contentStr);
            
			}
			
			else 
			{
                $keyword_length = mb_strwidth(preg_replace('/[\x00-\x7F]/','',$this->keyword),'utf-8')+str_word_count($this->keyword)*2;

                $weixin_keyword_allow_length = 16;
                $weixin_keyword_allow_length = apply_filters('weixin_keyword_allow_length',$weixin_keyword_allow_length);
        
                if($keyword_length > $weixin_keyword_allow_length)
				{
                    $weixin_keyword_too_long = "有点长==";
                    $weixin_keyword_too_long = apply_filters('weixin_keywords_too_long',$weixin_keyword_too_long);
                    echo sprintf($textTpl, $weixin_keyword_too_long);
                }
				elseif( !empty( $this->keyword ))
				{
                    $this->query();
                    if($this->articleCount == 0)
					{
                        $weixin_not_found = "抱歉，没有找到与【{$this->keyword}】相关的文章。。。 ";
                        $weixin_not_found = apply_filters('weixin_not_found', $weixin_not_found, $this->keyword);
                        echo sprintf($textTpl, $weixin_not_found);
                    }
					else
					{
                        echo sprintf($picTpl,$this->articleCount,$this->items);
                    }
                }
            }
        }
		else
		{
            echo "";
            exit;
        }
    }

    private function query()
	{
        global $wp_query;

        $weixin_count = 5;
        $weixin_count = apply_filters('weixin_count',$weixin_count);

        $weixin_query_array = array('s' => $this->keyword, 'posts_per_page' => $weixin_count , 'post_status' => 'publish' );
        $weixin_query_array = apply_filters('weixin_query',$weixin_query_array);

        $wp_query->query($weixin_query_array);

        if(have_posts())
		{
            while (have_posts()) 
			{
                the_post();

                global $post;

                $title =get_the_title(); 
                $excerpt = get_post_excerpt($post);

                $thumbnail_id = get_post_thumbnail_id($post->ID);
                if($thumbnail_id )
				{
                    $thumb = wp_get_attachment_image_src($thumbnail_id, 'thumbnail');
                    $thumb = $thumb[0];
                }
				else
				{
                    $thumb = get_post_first_image($post->post_content);
                }

                if(!$thumb && WEIXIN_DEFAULT)
				{
                    $thumb = WEIXIN_DEFAULT;
                }

                $link = get_permalink();

                $items = $items . $this->get_item($title, $excerpt, $thumb, $link);

            }
        }

        $this->articleCount = count($wp_query->posts);
        if($this->articleCount > $weixin_count) $this->articleCount = $weixin_count;

        $this->items = $items;
    }

    public function get_item($title, $description, $picUrl, $url)
	{
        if(!$description) $description = $title;

        return
        '
        <item>
            <Title><![CDATA['.$title.']]></Title>
            <Discription><![CDATA['.$description.']]></Discription>
            <PicUrl><![CDATA['.$picUrl.']]></PicUrl>
            <Url><![CDATA['.$url.']]></Url>
        </item>
        ';
    }

    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];    
                
        $weixin_token = apply_filters('weixin_token',WEIXIN_TOKEN);
        if(isset($_GET['debug']))
		{
            echo "\n".'WEIXIN_TOKEN：'.$weixin_token;
        }
        $tmpArr = array($weixin_token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
        
        if( $tmpStr == $signature )
		{
            return true;
        }
		else
		{
            return false;
        }
    }
}

if(!function_exists('get_post_excerpt'))
{

    function get_post_excerpt($post)
	{
        $post_excerpt = strip_tags($post->post_excerpt); 
        if(!$post_excerpt)
		{
            $post_excerpt = mb_substr(trim(strip_tags($post->post_content)),0,120);
        }
        return $post_excerpt;
    }
}

if(!function_exists('get_post_first_image'))
{

    function get_post_first_image($post_content)
	{
        preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', $post_content, $matches);
        if($matches)
		{       
            return $matches[1][0];
        }
		else
		{
            return false;
        }
    }
}

if(!function_exists('wpjam_search_orderby'))
{

    add_filter('posts_orderby_request', 'wpjam_search_orderby');
    function wpjam_search_orderby($orderby = '')
	{
        global $wpdb,$wp_query;

        $keyword = stripslashes($wp_query->query_vars[s]);

        if($keyword)
		{ 

            $n = !empty($q['exact']) ? '' : '%';

            preg_match_all('/".*?("|$)|((?<=[\r\n\t ",+])|^)[^\r\n\t ",+]+/', $keyword, $matches);
            $search_terms = array_map('_search_terms_tidy', $matches[0]);

            $case_when = "0";

            foreach( (array) $search_terms as $term )
			{
                $term = esc_sql( like_escape( $term ) );

                $case_when .=" + (CASE WHEN {$wpdb->posts}.post_title LIKE '{$term}' THEN 3 ELSE 0 END) + (CASE WHEN {$wpdb->posts}.post_title LIKE '{$n}{$term}{$n}' THEN 2 ELSE 0 END) + (CASE WHEN {$wpdb->posts}.post_content LIKE '{$n}{$term}{$n}' THEN 1 ELSE 0 END)";
            }

            return "({$case_when}) DESC, {$wpdb->posts}.post_modified DESC";
        }
		else
		{
            return $orderby;
        }
    }
}
