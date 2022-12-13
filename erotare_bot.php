<?php

require_once '/home/dbldihnu/php/lib/vendor/autoload.php';
require_once '/home/dbldihnu/php/lib/phpQuery-onefile.php';
require_once '/home/dbldihnu/kirinuki-erotaro.com/wp/wp-load.php';

use DeepL\Translator;

/* 変数定義-----------------------------------------------------------*/
$url = "https://www.xvideos.com/?k=moonforce&sort=random";	//取得URL	
//$url = "https://www.xvideos.com/?k=moonforce&sort=rating";	//取得URL	
//$url = "https://www.xvideos.com/?k=moonforce&sort=relevance";	//取得URL	
$num = 9;	// 表示させたい件数
$i = 1;
/*------------------------------------------------------------------ */
	
/* simplexml_load_file で自己署名証明書を許容する----------------------*/
$context = stream_context_create(array('ssl'=>array(
    'allow_self_signed'=> true,
    'verify_peer' => false,
)));
libxml_set_streams_context($context);
/*------------------------------------------------------------------ */
	
$authKey = "b312bd57-b9a3-9cc9-0dc5-c2078f600f5b:fx"; 
$translator = new Translator($authKey);
	
function curl_get_contents( $url, $timeout = 120 ){
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt( $ch, CURLOPT_HEADER, false );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
    $result = curl_exec( $ch );
    curl_close( $ch );
    return $result;
}

$category_id = "";
$cat_match = "";
// 投稿にあるすべてのカテゴリー名を繰り返し表示
function get_categories_foreach($cat_match, $category_id, $category_name){
	$categories = get_categories();
	foreach( $categories as $cat ){
		$cat_name = $cat->name;
		$cat_id = $cat->cat_ID;
		foreach($cat_match as $vals){
			if($vals == $cat_name){
				$category_id = $category_id.$cat_id.",";
				$category_name = $category_name.$cat_name.",";
			}
		}
	}
	return array($category_id, $category_name);
}

//HTMLソースをまるごと取得
	$HTMLData = curl_get_contents($url , 120);
	//DOM変換して要素をオブジェクト化する
	$dom = phpQuery::newDocument($HTMLData);
	
//タイトル、サムネ、埋め込み動画を取得
foreach($dom['.title']->find('a') as $title_a)
{
	//重複等で記事の取得に失敗したら値を初期化して記事を再取得する。
	$category_id = "";
	$category_name = "";
	
    if($num >= $i){
        $title = $title_a->getAttribute('title');
        $link = $title_a->getAttribute('href');
		echo "<h2>".$i."記事目</h2>";
		echo "【原文】：".$title."<br/>\n";
		
		if(preg_match('/video.{8}/', $link)){
        	$link = 'https://www.xvideos.com'.$link;
		}
		echo "【元動画のリンク】：".$link."<br>\n";	//リンク取得  
		//$link = "https://www.xvideos.com/video68613725/_h_sex_https_bit.ly_3dtqn6q";	
		
		//リンク先 ($link) のHTMLソースを取得----------------------------------------------------
        $html = curl_get_contents($link, 120); 
 
        //HTMLソースをDOMdocumentに変換
        $link_html_source = phpQuery::newDocument($html);
        //---------------------------------------------------------------------------------------

		//サムネ取得
		$thumb = $link_html_source->find('img')->attr('src');
		$thumb_url = $thumb;
		$thumb_html = '<a href="'.$link.'" target="_blank"><img src = '.$thumb_url.'></a>';
		
		//埋め込み動画取得
		foreach($link_html_source->find('.copy-link.force-one-line')->find('input') as $iframe){
            $iframe = $iframe->getAttribute('value');
        }
		$html = $thumb_html.$iframe;
		//エロタレ記事重複チェック-----------------------------------------------------------
		$api = "2dddab891e4d8f66fe0b88553f910622";
		$link_encode = urlencode($link);
		$erotare_url = "https://api.movie.eroterest.net/api_duplication/?key=".$api."&url=".$link_encode;
		$json = curl_get_contents($erotare_url, 120); 
		$json = mb_convert_encoding($json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
		$json = json_decode($json, true);
		
		foreach($json["pages"] as $item){
			if($item["is_duplicate"]){
				echo "※error ".$error_code."：is_duplicate：タイトル重複or表示遅延対象の記事です。";
				continue 2;
			}
        }
		
		//同一サムネなら別記事を取得する。（投稿IDをぶん回してサムネリンクと一致したら記事取得しなおす）
		$get_post_id = get_the_latest_ID();
		for($id = 1656; $id <= $get_post_id; $id++){
			if( get_post_status( $id ) ){	//投稿IDがあれば本文取得
				$post_info = get_post( $id );
				$post_content = $post_info->post_content;
				$post_title = $post_info->post_title;
			}else{	//投稿IDに該当が無ければ本文取得を無視
				continue;
			}
			
			if(preg_match('{'.$link.'}', $post_content)){	//元リンクが一致したら記事取得しなおす
				echo "【wp投稿時のタイトル】：".$post_title."<br/>";
				echo "【パーマリンク】：".get_permalink( $id )."<br/>";
				
				foreach($json["pages"] as $item){
					$erotare_title = $item["title"];
					echo "【エロタレのタイトル】：".$erotare_title."<br/>";
					if(preg_match('{'.$post_title.'}', $erotare_title)){
						break;
					}else{
						echo "※過去にwpに投稿したが、同一動画としてエロタレに取得されなかった記事です。<br/>※タイトルを再変換して投稿しなおします。<br/>-----------↓タイトル変換後↓------------<br/>";
						$title = $post_title;
						goto endloop;
					}
				}
				
				echo "※error：過去記事で同一動画を掲載した。（元リンクが過去記事と一致）<br/>";
				continue 2;
			}
			else if(preg_match('{'.$thumb_url.'}', $post_content)){	//サムネリンクが一致したら記事取得しなおす
				echo "【WPに投稿された時のタイトル】：".$post_title."<br/>";
				echo "【パーマリンク】：".get_permalink( $id )."<br/>";
				
				foreach($json["pages"] as $item){
					$erotare_title = $item["title"];
					echo "【エロタレのタイトル】：".$erotare_title."<br/>";
					if(preg_match('{'.$post_title.'}', $erotare_title)){
						break;
					}else{
						echo "※過去にwpに投稿したが、同一動画としてエロタレに取得されなかった記事です。<br/>※タイトルを再変換して投稿しなおします。<br/>-----------↓タイトル変換後↓------------<br/>";
						$title = $post_title;
						goto endloop;
					}
				}
				
				echo "※error：過去記事で同一動画を掲載した。（サムネが過去記事と一致）<br/>";
				continue 2;
			}

		}

        //-----------------------------------------------------------------------------------
		
		endloop:
		
		$array = [
			/*０*/["巨乳","爆乳","おっぱい","胸","パイズリ","ぱいずり","ぽっちゃり","でかぱい","グラマー","スタイル","抜群","美貌","でか","カップ","グラビア","級","ボディ"," ムチムチ","モデル"],//巨乳８，５６
			/*１*/["中出し","中に","出して",'腟内'],	//中出し４
			/*２*/["熟女","五十路","おばさん","BBA"],	//熟女５３，５４
			/*３*/["貧","小柄","小さい"],	//貧乳５５
			/*４*/["jk","女子高生","生徒","制服",'18歳','17歳','16歳','j系','放課後'],	//ｊｋ９、女子高生５２
			/*５*/["jd","女子大生","学生"],	//女子大生５９
			/*６*/["人妻","夫","NTR",'主人','不貞','秘密','奥さん','忘れ','不倫','寂','別れ','浮気','裏切','寝取','内緒'],	//NTR２６、寝取られ２７、痴女２１、不倫３２
			/*７*/['フェラ','しゃぶ','舐','ふぇら','根本','咥え','顔射','口内'],	//フェラ５９
			/*８*/['ナンパ','連れ','口説','ついていって'],	//ナンパ１７
			/*９*/['妻','奥さん'],	//人妻６
			/*10*/['初撮り','ハメ撮り','主観','個人','プライベート'],	//ハメ撮り３０
			/*11*/['ライブ','チャット','生配信'],	//ライブチャット３９
			/*12*/['mm','マジックミラー'],	//MM号２８、マジックミラー６１
			/*13*/['整体','マッサージ','ローション'],	//マッサージ１４
			/*14*/['スケベ','淫乱','アクメ','ヤリマン','痴女']	//痴女２１
		];
		


		$key_cnt = 0;
		foreach($array as $keys){
			foreach($keys as $vals){
				if(preg_match('{'.$vals.'}i', $title)){
					switch($key_cnt){
						case 0: $cat_match = ['巨乳','おっぱい']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 1: $cat_match = ['中出し']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 2: $cat_match = ['熟女','おばさん']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 3: $cat_match = ['貧乳']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 4: $cat_match = ['jk','ｊｋ','女子高生']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 5: $cat_match = ['女子大生']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 6: $cat_match = ['NTR','寝取られ','痴女','不倫']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 7: $cat_match = ['フェラ']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 8: $cat_match = ['ナンパ']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 9: $cat_match = ['人妻']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 10: $cat_match = ['ハメ撮り']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 11: $cat_match = ['ライブチャット']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 12: $cat_match = ['MM号','マジックミラー']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 13: $cat_match = ['マッサージ']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						case 14: $cat_match = ['痴女']; list($category_id, $category_name) = get_categories_foreach($cat_match, $category_id, $category_name); break;
						default: break;
					}
					break;
				}
			}
			$key_cnt++;
		}
		
		//括弧および括弧内の文章を削除
		$title = preg_replace("/\【.+?\】/", "", $title);	
		$title = preg_replace("/\[.+?\]/", "", $title);
		$title = preg_replace("/\『.+?\』/", "", $title);
	
		$title = preg_replace("/http.:\/\/.+\/.{7}/", "", $title);	//URL表記を削除
		$title = preg_replace("/[#@\/]/", "", $title);	//記号を削除
		
		//deepl二重変換
		$result = $translator->translateText("$title", null, 'en-US');
		//echo $result."<br>\n";
		$result = $translator->translateText("$result", null, 'ja');
		$title = $result;
		
		$title = preg_replace("/(.{3,9})\1+/", "", $title);	//3~9文字以上の連続した文字列を削除
		$title = preg_replace("/[a-z]{4,20}/i", "", $title);	//4~20文字以上の英語文字列を削除
		$title = preg_replace("/。|!/", "！", $title);	//読点をビックリマークに変換
	
		//エロタレ記事重複チェック-----------------------------------------------------------
		$title_encode = urlencode($title);
		$erotare_url = "https://api.movie.eroterest.net/api_duplication/?key=".$api."&url=".$link_encode."&title=".$title_encode;
		$json = curl_get_contents($erotare_url, 120); 
		$json = mb_convert_encoding($json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
		$json = json_decode($json, true);
		//print_r($json)."<br/>";
		
		$error_code = $json["error_code"];
		if($error_code == 203){
			echo "※error ".$error_code."：タイトルが同一(収集されない)";
			continue;
		}
		if($error_code == 205){
			echo "※error ".$error_code."：タイトルが重複(収集されない)";
			continue;
		}
		
        foreach($json["pages"] as $item){
			if($item["is_duplicate"]){
				echo "※error：is_duplicate：タイトルが重複しています。（APIにタイトル入力）";
				continue 2;
			}
        }
        //-----------------------------------------------------------------------------------	
    	
		$category_id = $category_id."19,13,3,60";	//エロ13、エロ動画3、xvideos60,えろ19　デフォルト
		
		//冒頭括弧の優先順位を決める
		$cat_priority = ["ライブチャット","MM号","jk","女子大生","熟女","ナンパ","人妻","マッサージ","痴女","フェラ","巨乳","ハメ撮り"];
		$category_name_explode = explode(',', $category_name);
		foreach($category_name_explode as $vals){
			foreach($cat_priority as $item){
				if(preg_match('{'.$item.'}i', $vals)){
					$headline = "【".$vals."】";
					goto label;
				}
			}
			$headline = "";
		}
		label:
		$title = $headline.$title;
		
		//プラグイン用に出力してるやつ
		echo "【タイトル】：".$title."<br/>";
		echo "【カテゴリ】：".$category_name.$category_id."<br>\n";	//カテゴリ出力
		echo $thumb_html."<br/>\n";
		echo "【サムネリンク】：".$thumb_url."<br/>\n";
		echo $iframe."<br/>";
		
		// 投稿オブジェクトのパーマリンクを取得
		$recent = get_posts('post_type=diary');
		if (count($recent) > 0) {
			$recent_id = $recent[0]->ID;
			$recent_url = get_permalink($recent_id);

			echo $recent_url."<br/>";
			
		}
		
 		/*ワードプレスに投稿---------------------------------------------------------*/
 		//function postToBlog($title,$html,$thumb_url,$category_id){
 		    date_default_timezone_set('Asia/Tokyo');
 			$time = date('H-i-s')."<br/>\n";
			$wp_error = 0;
			$my_post = array(
				'post_title' => $title,
				'post_content' => $html,
				'post_status' => 'publish',//'publish,private
				'post_author' => 1,
				'post_category' => explode(',', $category_id),
				'post_name' => $time
			);
		
			// 保存前に一旦サニタイズをオフに
			remove_filter('content_save_pre', 'wp_filter_post_kses');
			remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');

			//投稿をデータベースへ追加して保存
			$post_id = wp_insert_post( $my_post, $wp_error );

			// セキュリティの都合上保存が終わったらすぐに戻す
			add_filter('content_save_pre', 'wp_filter_post_kses');
			add_filter('content_filtered_save_pre', 'wp_filter_post_kses');


				//restore_current_blog();
		//}
		/*--------------------------------------------------------------------------------------------*/
		//postToBlog($title,$html,$thumb_url,$category_id);


		$upload_dir = wp_upload_dir(); // 初期化

		//サムネイル画像のデータを取得
		$image = curl_get_contents( $thumb_url );


		$filename = basename( $thumb_url );
		if( wp_mkdir_p( $upload_dir['path'] ) ) {
			$file = $upload_dir['path']. '/'. $filename;
		} else {
			$file = $upload_dir['basedir']. '/'. $filename;
		}
		file_put_contents( $file, $image );
		$wp_filetype = wp_check_filetype( $filename, null );
		$attachment = [
			'post_mime_type' => $wp_filetype['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit'
		];
		$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
		require_once('/home/dbldihnu/kirinuki-erotaro.com/wp/wp-admin/includes/image.php' );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
		wp_update_attachment_metadata( $attach_id, $attach_data );
		set_post_thumbnail( $post_id, $attach_id );

		
        $i++;
		echo "<br></br>";
    }else if($num == $i){
        continue;
 		echo 'continue';
        $i++;
    }
}