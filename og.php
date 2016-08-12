<?php

if (!defined('SMF')) 
	die('Hacking attempt...');

function get_og_data($url) {
	global $smcFunc, $modSettings;
	$en_url = urlencode($url);
	if (filter_var($url, FILTER_VALIDATE_URL) === FALSE || !empty($modSettings['og_ext_chk']) && $modSettings['og_ext_chk'] == 1 && !og_is_allowed($url))
		return(array('display'=>$url));
	if(($data = cache_get_data('og-info'.$en_url, 1800)) != null) {
		return json_decode($data, true);
	} else {
		$query = $smcFunc['db_query']('', 'SELECT vars, req_date FROM {db_prefix}og_cache WHERE url = {text:url}', array('url' => $en_url));
		if ($smcFunc['db_num_rows']($query) != 0) {
			$data = $smcFunc['db_fetch_assoc']($query);
			$smcFunc['db_free_result']($query);
			cache_put_data('og-info'.$en_url, $data['vars'], 1800);
			return json_decode($data['vars'], true);
		}
		$data = array();
		libxml_use_internal_errors(true);
		$doc = new DomDocument();
		$doc->loadHTML(file_get_contents($url,NULL,stream_context_create(array('http'=>array('method'=>"GET",'header'=>"Accept-language: en\r\nCookie: foo=bar\r\n",'user_agent'=>"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.73 Safari/537.36"))), 0, 20000));
		$xpath = new DOMXPath($doc);
		foreach ($xpath->query('//*/meta[starts-with(@property, \'og:\')]') as $meta) {
			if (isset($data['og:video:url']) && $meta->getAttribute('property') == 'og:video:url')
				$data['og:video:url2'] = $data['og:video:url'];
			if (isset($data['og:video:type']) && isset($data['og:video:url2']) && $meta->getAttribute('property') == 'og:video:type' && $meta->getAttribute('content') == 'application/x-shockwave-flash')
				$data['og:video:url'] = $data['og:video:url2'];
			else
				$data[$meta->getAttribute('property')] = $meta->getAttribute('content');
		}
		if (empty($data['og:title']) && $xpath->query('//title')->length)
			$data['og:title'] = $xpath->query('//title')->item(0)->textContent;
		if (empty($data['og:description']) && $xpath->query('/html/head/meta[@name="description"]/@content')->length)
			$data['og:description'] = $xpath->query('/html/head/meta[@name="description"]/@content')->item(0)->textContent;
		if (empty($data['og:image']) && $xpath->query('/html/head/link[@rel="image_src"]/@href')->length)
			$data['og:image'] = $xpath->query('/html/head/link[@rel="image_src"]/@href')->item(0)->textContent;
		if (empty($data['og:image']) && $xpath->query('/html/head/link[@rel="icon"]/@href')->length)
			$data['og:image'] = $xpath->query('/html/head/link[@rel="icon"]/@href')->item(0)->textContent;
		if (empty($data['og:video:url']) && $xpath->query('/html/head/link[@rel="video_src"]/@href')->length)
			$data['og:video:url'] = $xpath->query('/html/head/link[@rel="video_src"]/@href')->item(0)->textContent;
		if (empty($data['og:video:type']) && $xpath->query('/html/head/meta[@name="video_type"]/@content')->length)
			$data['og:video:type'] = $xpath->query('/html/head/meta[@name="video_type"]/@content')->item(0)->textContent;
		if (empty($data['og:video:width']) && $xpath->query('/html/head/meta[@name="video_width"]/@content')->length)
			$data['og:video:width'] = $xpath->query('/html/head/meta[@name="video_width"]/@content')->item(0)->textContent;
		if (empty($data['og:video:height']) && $xpath->query('/html/head/meta[@name="video_height"]/@content')->length)
			$data['og:video:height'] = $xpath->query('/html/head/meta[@name="video_height"]/@content')->item(0)->textContent;
		if (empty($data['og:description']) && empty($data['og:video:url']) && empty($data['og:image'])) {
			$oembed = array();
			foreach ($xpath->query('//*/link[contains(@type, \'+oembed\')]') as $meta)
				if(isset($oembed[$meta->getAttribute('type')]))
					continue;
				else
					$oembed[$meta->getAttribute('type')] = $meta->getAttribute('href');
			if (!empty($oembed['application/json+oembed']) && og_is_allowed($url))
				$data['oembed'] = og_get_oembed($oembed['application/json+oembed'],'json');
			else if (!empty($oembed['application/xml+oembed']) && og_is_allowed($url))
				$data['oembed'] = og_get_oembed($oembed['application/json+oembed'],'xml');
		}
		if (empty($data['og:video:url']) && $xpath->query('/html/head/meta[@property="twitter:player"]/@content')->length)
			$data['og:video:url'] = $xpath->query('/html/head/meta[@property="twitter:player"]/@content')->item(0)->textContent;
		if (!empty($data['vars']))
			unset($data['vars']);
		if (empty($data['og:url']))
			$data['og:url'] = $url;
		if (empty($data['og:title']))
			$data['display'] = $url;
		if (!empty($data['og:video']))
		if (!empty($data['og:video']))
			$data['og:video'] = preg_replace(array('/autoPlay=1/','/autoplay=1/','/autoplay=true/'),array('autoPlay=0','autoplay=0','autoplay=false'),$data['og:video']);
		if (!empty($data['og:video:url']))
			$data['og:video:url'] = preg_replace(array('/autoPlay=1/','/autoplay=1/','/autoplay=true/'),array('autoPlay=0','autoplay=0','autoplay=false'),$data['og:video:url']);
		$data['req_date'] = time();
		$smcFunc['db_insert']('replace', '{db_prefix}og_cache', array('url' => 'text', 'vars' => 'text', 'req_date' => 'int'),array($en_url, json_encode($data), $data['req_date']),array('url'));
		cache_put_data('og-info'.$en_url, json_encode($data), 1800);
	}
	return($data);
}
function og_get_oembed ($url, $type) {
	if (filter_var($url, FILTER_VALIDATE_URL) === FALSE)
		return;
	if ($type = 'json')
		return (json_decode(file_get_contents($url,NULL,stream_context_create(array('http'=>array('method'=>"GET",'header'=>"Accept-language: en\r\nCookie: foo=bar\r\n",'user_agent'=>"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.73 Safari/537.36"))), 0, 20000),true));
	if ($type = 'xml')
		return;
}
function og_bbc(&$bbc) {
	$bbc[] = array(
		'tag' => 'embed',
		'type' => 'unparsed_content',
		'content' => '$1',
		'validate' => 'og_bbc_validate',
		'disabled_content' => '$1',
	);
}

function og_bbc_validate (&$tag, &$data, &$disabled) {
	$data = phase_og_data(get_og_data($data));
}
function og_is_allowed($url) {
	global $modSettings;
	// This is here to protect against protocols that may be a security risk if allowed to embed openly.
	$host = parse_url($url, PHP_URL_HOST);
	if(!empty($modSettings['og_allowed']) && $modSettings['og_allowed'] != '')
		$allowed = preg_split('/\r\n|[\r\n]/', $modSettings['og_allowed']);
	else
		$allowed = array('animoto.com','chirb.it','clyp.it','collegehumor.com','deviantart.com','dotsub.com','embed.ly','facebook.com','flicker.com','flic.kr','funnyordie.com','huffduffer.com','hulu.com','ifixit.com','ifttt.com','kickstarter.com','mixcloud.com','nfb.ca','official.fm','polldaddy.com','portfolium.com','rdio.com','sapo.pt','screenr.com','scribd.com','slideshare.net','smugmug.com','ted.com','ustream.tv','viddler.com','videojug.com','vimeo.com','slideshare.net',);

	foreach ($allowed as $site)
		if (stripos($host, trim($site)))
			return (true);

	return false;

}
function phase_og_data($data) {
	if (!empty($data['display']))
		return('<a href="'.$data['display'].'" target="_blank">'.$data['display'].'</a>');

	return ('
		<div class="og-embed">
			<div class="og-embed-title"><a href="'.$data['og:url'].'" target="_blank">
				'.(empty($data['og:site_name']) ? '' : $data['og:site_name'].' - ').(empty($data['og:title']) ? '' : $data['og:title']).'
			</a></div>'
			.(!empty($data['og:video']) || !empty($data['og:video:url']) ? phase_og_video($data) : (!empty($data['og:audio']) ? phase_og_audio($data) : (!empty($data['oembed']['html']) ? phase_og_oembed($data['oembed']) : '')))
			.'
			<div class="og-embed-description">
				'.(empty($data['og:image']) ? '' : '<img class="og-embed-image" src="'.$data['og:image'].'" />').(empty($data['og:description']) ? '' : $data['og:description']).'<br style="clear:both;" />
			</div>
		</div>');

}
function phase_og_video ($data) {
		return('
			<div class="og-embed-media">
				<center>
					<embed class="og-embed-video" src="'
						.(!empty($data['og:video:url']) ? $data['og:video:url'] : (!empty($data['og:video']) ? $data['og:video'] : ''))
						.'" autostart="false"'
						.(!empty($data['og:video:type']) ? ' type="'.$data['og:video:type'].'"' : '').'></embed>
				</center>
			</div>');
}
function phase_og_audio ($data) {
	return ('
			<div class="og-embed-media">
				<center>
					<audio src="'
						.(!empty($data['og:audio']) ? $data['og:audio'] : '').'"'
						.(!empty($data['og:audio:type']) ? ' type="'.$data['og:audio:type'].'"' : '')
						.' class="og-embed-audio" controls>
					</audio>
				</center>
			</div>');
}
function phase_og_oembed ($data) {
	$data['html'] = preg_replace('!\s+!', " ", $data['html']);
	return ('
			<div class="og-embed-media">
				<center>
					'.$data['html'].'
				</center>
			</div>
			');
}
function scheduled_og_prune () {
	global $smcFunc;
	$smcFunc['db_query']('', 'DELETE FROM {db_prefix}og_cache WHERE req_date < '.(time()-2419200));
	return true;
}
function og_admin (&$subActions) {
	$subActions['og_settings'] = 'og_settings';
}
function og_admin_areas(&$admin_areas) {
	global $txt;
	$admin_areas['config']['areas']['modsettings']['subsections']['og_settings'] = array($txt['og_settings']);
}
function og_settings ($return_config = false) {
	global $scripturl, $context, $txt;
	$config_vars = array(
		array('check', 'og_ext_chk', 'subtext' => $txt['og_ext_chk_desc']),
		array('large_text', 'og_allowed', 'subtext' => $txt['og_allowed_desc']),
	);

	if (!empty($return_config))
		return $config_vars;

	$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=og_settings';
	$context['settings_title'] = $txt['og_settings'];

	if (isset($_GET['save'])) {
		checkSession();
		$save_vars = $config_vars;
		saveDBSettings($save_vars);
		redirectexit('action=admin;area=modsettings;sa=og_settings');
	}
	prepareDBSettingContext($config_vars);
}
?>
