<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Http\Controllers\Controller;
use Symfony\Component\DomCrawler\Crawler;
use RuntimeException;

require_once base_path('vendor/html_dom/simple_html_dom.php');

class WebscraperController extends Controller
{
    private function str_get_html()
    {
        return call_user_func_array('\str_get_html', func_get_args());
    }
    
    private function parse_url($base_url,$main_url)
    {
        $base_url_array = parse_url($base_url);
        $base_url = $base_url_array['scheme'].'://'.$base_url_array['host'].'/';
        
        $main_url_array = parse_url(trim($main_url));
        $final_url = $main_url;
        if(!isset($main_url_array['host']))
        {
            $final_url = $base_url.str_replace('../','',trim($main_url));
        }
        
        return trim($final_url);
    }
    
    public function fetch_data()
    {
        $client = new Client();
        $url = 'http://archive-grbj-2.s3-website-us-west-1.amazonaws.com/topics/143-banking-finance.html';
        $response = $client->request('GET', $url);
        
        if($response->getStatusCode() == 200)
        {
            $htmlstr = $response->getBody();
            $main_obj = $this->str_get_html($htmlstr);
            if($main_obj)
            {
                $return_array = array();
                $first_container = $main_obj->find("body div.container div.main-content div#section-2 div.box1 div.article-list div.records",0);
                //echo $first_container->innertext;
                if($first_container)
                {
                    foreach($first_container->find('div.record div.info') as $element) 
                    {
                        $article_title = $element->find('h2',0)->plaintext;
                        $article_url = $element->find('h2 a',0)->href;
                        $article_url = $this->parse_url($url,$article_url);
                        $article_date = $element->find('div.meta div.date',0)->plaintext;
                        if(strlen(trim($article_date)) > 0)
                        {
                            $temp_d = strtotime(trim($article_date));
                            $temp_de = date('Y-m-d',$temp_d);
                            $article_date = $temp_de;
                        }
                        
                        $author_name = trim($element->find('div.meta div.author a',0)->plaintext);
                        $author_url = $element->find('div.meta div.author a',0)->href;
                        $author_url = $this->parse_url($url,$author_url);
                        $author_twitter_link = '';
                        $author_twitter_text = '';
                        $author_bio = '';
                        if((strtolower($author_name) != "staff"))
                        {
                            if((strlen($author_url) > 0) && (!array_key_exists($author_name,$return_array)))
                            {
                                $return_array[$author_name] = array();
                                $return_array[$author_name]['articles'] = array();
                                $return_array[$author_name]['authorName'] = $author_name;
                                $return_array[$author_name]['authorUrl'] = $author_url;
                                $return_array[$author_name]['authorTwitterLink'] = '';
                                $return_array[$author_name]['authorTwitterName'] = '';
                                $return_array[$author_name]['authorBio'] = '';
                                
                                $author_client = new Client();
                                $author_response = $author_client->request('GET', $author_url);
                                $author_htmlstr = $author_response->getBody();
                                $author_main_obj = $this->str_get_html($author_htmlstr);
                                $author_obj = $author_main_obj->find('div.main-content div.author-bio div.author-info div.abstract',0);
                                //echo $author_obj->innertext; die;
                                if($author_obj)
                                {
                                    $author_twitter_link = $author_obj->find('a',0)->href;
                                    $author_twitter_text = $author_obj->find('a',0)->plaintext;
                                    $author_bio = $author_obj->plaintext;
                                    
                                    $parse_twitter_url = parse_url($author_twitter_link);
                                    if(isset($parse_twitter_url['host']))
                                    {
                                        if((strtolower(trim($parse_twitter_url['host'])) == 'twitter.com') || strtolower(trim($parse_twitter_url['host'])) == 'www.twitter.com')
                                        {
                                            $return_array[$author_name]['authorTwitterLink'] = $author_twitter_link;
                                            $return_array[$author_name]['authorTwitterName'] = $author_twitter_text;
                                        }
                                    }
                                    
                                            
                                    $return_array[$author_name]['authorBio'] = trim($author_bio);
                                }
                            }
                            
                            $return_array[$author_name]['articles'][] = array('articleUrl'=>$article_url,'articleDate'=>$article_date,'article_title'=>$article_title);
                            //echo $author_twitter_link.' : '.$author_twitter_text.' : '.$author_bio.' : '.$article_title.' : '.$article_url.' : '.$article_date.' : '.$author_name.' : '.$author_url.'<br>';
                        }
                        
                    }
                    
                    $final_array = array();
                    
                    foreach($return_array as $key=>$value)
                    {
                        $temp_array = array();
                        $temp_array['authorName'] = $value['authorName'];
                        $temp_array['authorUrl'] = $value['authorUrl'];
                        $temp_array['authorTwitterLink'] = $value['authorTwitterLink'];
                        $temp_array['authorTwitterName'] = $value['authorTwitterName'];
                        $temp_array['authorBio'] = $value['authorBio'];
                        $temp_array['articles'] = array();
                        
                        foreach($value['articles'] as $articles)
                        {
                            $temp_articles = array();
                            $temp_articles['articleTitle'] = $articles['article_title'];
                            $temp_articles['articleUrl'] = $articles['articleUrl'];
                            $temp_articles['articleDate'] = $articles['articleDate'];
                            
                            $temp_array['articles'][] = $temp_articles;
                        }
                        
                        $final_array[] = $temp_array;
                    }
                    
                    echo "<pre>";
                    print_r($final_array);
                }
            }else
            {
                echo "unexpected error occured!";
            }
        }else
        {
            echo "unexpected error occured!";
        }
    }
}