<?php
    
    
    namespace instagram_get_my_popular_tag_list;
    
    use GuzzleHttp\Exception\GuzzleException;
    
    class instagram_get_my_popular_tag_list{
        
        public $user       = null;
        public $username   = null;
        public $session_id = null;
        public $csrftoken  = null;
        public $doc_id     = '3808023159239182';
        public $posts      = null;
        public $cache_path = (__DIR__).'/../cache';
        public $cache_time = 10; //Minute
        
        public function __construct($username = null, $session_id = null){
            
            $this->username   = $username;
            $this->session_id = $session_id;
            
            $this->headers = [
                'X-IG-Connection-Type' => 'WIFI',
                'X-IG-Capabilities'    => '3brTBw==',
                'X-IG-App-ID'          => '567067343352427',
                'User-Agent'           => 'Instagram 35.0.0.20.96 Android (22/5.1.1; 160dpi; 540x960; Google/google; google Pixel 2; x86; qcom; tr_TR; 95414347)',
                'Accept-Language'      => 'tr-TR, en-US',
                'Cookie'               => $this->create_cookie(),
                'X-FB-HTTP-Engine'     => 'Liger',
                'Host'                 => 'i.instagram.com',
                'X-Bloks-Version-Id'   => 'fe808146fcbce04d3a692219680092ef89873fda1e6ef41c09a5b6a9852bed94',
            ];
            
        }
        
        public function get_my_popular_tag_list(){
        
            $posts = $this->get_user_post_detail();
            
            $results = [];
            if($posts != null){
                foreach($posts as $post){
                    $post_popular_tags = $post['post_popular_tags'];
    
                    if(isset($post_popular_tags['hashtag'])){
                        foreach($post_popular_tags['hashtag'] as $hastags){
                            if($hastags['value'] > 0){
                                $results[$hastags['name']] = (isset($results[$hastags['name']])?$results[$hastags['name']] + $hastags['value']:$hastags['value']);
                            }
                        }
                    }
                    
                }
            }
    
            arsort($results);
            return $results;
        }
        
        public function get_user_post_detail($posts = null){
            
            $posts = $posts??$this->posts;
            if($posts == null){
                $this->get_user_posts();
            }
            
            $results = [];
            foreach($this->posts->edges as $post){
                
                $post_insights = $this->get_post_insights($post->node->id);
                $results[] = [
                    'post_id' => $post->node->id,
                    'post_insights' => $post_insights,
                    'post_popular_tags' => $this->get_post_popular_tags($post_insights),
                ];
            }
            
            return $results;
        }
        
        public function get_post_popular_tags($post_insights = null){
            
            $result = [];
            if(isset($post_insights->hashtags_impressions)){
                $tags = $post_insights->hashtags_impressions;
                if($tags){
                    $result['organic'] = $tags->organic->value;
    
                    if($tags->hashtags->count != null){
                        foreach($tags->hashtags->nodes as $hashtag){
                            $result['hashtag'][] = [
                                'value' => $hashtag->organic->value,
                                'name' => $hashtag->name,
                            ];
                        }
                    }
                }
            }
            
            return $result;
        }
        
        public function get_post_insights($post_id = null){
            
            $cache = $this->cache($post_id);
            if($cache == false){
                if($post_id != null){
                    $post_param = [
                        'surface'        => 'post',
                        'doc_id'         => $this->doc_id,
                        'locale'         => 'tr_TR',
                        'vc_policy'      => 'insights_policy',
                        'signed_body'    => 'SIGNATURE.',
                        'strip_nulls'    => 'true',
                        'strip_defaults' => 'true',
                        'query_params'   => ('{"query_params":{"access_token":"","id":"'.$post_id.'"}}'),
                    ];
                    
                    $link                   = "https://i.instagram.com/api/v1/ads/graphql/";
                    $user_general_statistic = $this->request($link, null, $post_param);
                    $user_general_statistic = json_decode($user_general_statistic);
                    $user_general_statistic = $user_general_statistic->data->instagram_post_by_igid->inline_insights_node->metrics;
                    
                    if($user_general_statistic != null){
                        $this->cache($post_id, $user_general_statistic);
                    }else{
                        $this->cache($post_id, "{}",true);
                    }
                }
                else{
                    $user_general_statistic = false;
                }
            }
            else{
                $user_general_statistic = $cache;
            }
            
            return $user_general_statistic;
        }
        
        public function get_user_posts($username = null, $posts = null){
            
            $username = $username??$this->username;
            $cache    = $this->cache($username);
            if($cache == false){
                
                $query_hash_posts = $this->get_instagram_post_queryhash();
                $user_id          = $this->get_instagram_user_id($username);
                
                $link           = 'https://www.instagram.com/graphql/query/?query_hash='.$query_hash_posts.'&variables={"id":"'.$user_id.'","first":50}';
                $get_posts_json = file_get_contents($link);
                $get_posts_json = json_decode($get_posts_json);
                $get_posts_json = $get_posts_json->data->user->edge_owner_to_timeline_media;
                
                $this->cache($username, $get_posts_json);
            }
            else{
                $get_posts_json = $cache;
            }
            
            $this->posts = $get_posts_json;
            
            return $get_posts_json;
            
        }
        
        public function get_instagram_user_id($username = null){
            
            $username = $username??$this->username;
            
            if($username != null){
                $link = 'https://www.instagram.com/web/search/topsearch/?query='.$username;
                
                $json = file_get_contents($link);
                $json = json_decode($json);
                
                $user_id = 0;
                foreach($json->users as $user){
                    if($username == $user->user->username){
                        $user_id = $user->user->pk;
                    }
                }
                
                return $user_id;
            }
            
            return false;
            
        }
        
        private function get_instagram_post_queryhash(){
            $link   = 'https://www.instagram.com/static/bundles/es6/Consumer.js/260e382f5182.js';
            $get_js = file_get_contents($link);
            preg_match('|l.pagination},queryId:"(.*?)"|is', $get_js, $query_hash);
            $this->query_hash = $query_hash[1];
            return $query_hash[1];
        }
        
        public function cache($name, $desc = false, $json = false){
            
            $cache_file_path = (__DIR__).'/../cache/';
            $cache_file      = $cache_file_path.$name.'.json';
            
            //if(file_exists($cache_file) and time() <= strtotime('+'.$this->cache_time.' minute', filemtime($cache_file))){
            
            if(file_exists($cache_file)){
                return json_decode(file_get_contents($cache_file));
            }
            else if($desc !== false){
                if($json == true){
                    file_put_contents($cache_file, $desc);
                }
                else{
                    file_put_contents($cache_file, json_encode($desc));
                }
                return $desc;
            }
            else{
                return false;
            }
        }
        
        public function request($link = '', $session_id = null, $data = null){
            
            if(!is_array($data)){
                parse_str($data, $data);
            }
            
            $session_id = $session_id??$this->session_id;
            
            if($session_id != null){
                
                try{
                    $client = new \GuzzleHttp\Client([
                        'verify' => false,
                    ]);
                    
                    $res  = $client->request('POST', $link, [
                        'headers'     => $this->headers,
                        'form_params' => $data,
                    ]);
                    $body = $res->getBody()->getContents();
                    
                    return $body;
                }
                catch(GuzzleException $err){
                    echo $err->getMessage();
                    return false;
                }
            }
            else{
                return 'Please Enter session_id';
            }
            
        }
        
        public function get_csrftoken(){
            
            $link = 'https://www.instagram.com/';
            try{
                
                $cache_file = $this->cache_path.'/queryhashs/csrftoken.json';
                if(file_exists($cache_file) and time() <= strtotime('+'.$this->cache_time.' minute', filemtime($cache_file))){
                    $csrftoken = file_get_contents($cache_file);
                }
                else{
                    $client = new \GuzzleHttp\Client([
                        'verify' => false,
                    ]);
                    
                    $res  = $client->request('GET', $link);
                    $body = $res->getBody();
                    
                    preg_match('|{"config":{"csrf_token":"(.*?)"|is', $body, $csrftoken);
                    
                    $csrftoken       = $csrftoken[1];
                    $this->csrftoken = $csrftoken;
                    
                    file_put_contents($cache_file, $csrftoken);
                }
                
            }
            catch(Exception $e){
                return $e->getMessage();
            }
            
            return $csrftoken;
            
        }
        
        private function create_cookie(){
            
            $cookies = [
                //'shbts'               => '1610656162.9076033',
                'sessionid' => $this->session_id,
                //'mid'                 => 'YACpOAABAAFqqNqmVntjXQzOuElN',
                //'ds_user'             => $this->username,
                //'ds_user_id'          => $this->user_id,
                //'csrftoken'           => $this->csrftoken,
                //'shbid'               => '19110',
                //'rur'                 => 'VLL',
                //'urlgen'              => '{\\',
            ];
            
            $cookie_text = '';
            foreach($cookies as $cookie => $value){
                $cookie_text .= $cookie.'='.$value.'; ';
            }
            return $cookie_text;
            
        }
        
        
    }