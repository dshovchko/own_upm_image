<?php

// This is a PLUGIN TEMPLATE.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ('abc' is just an example).
// Uncomment and edit this line to override:
# $plugin['name'] = 'abc_plugin';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 0;

$plugin['version'] = '4.8.1.20200729';
$plugin['author'] = 'Dmitry Shovchko';
$plugin['author_uri'] = 'http://github.com/dshovchko';
$plugin['description'] = 'More powerful image display';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
# $plugin['order'] = 5;

// Plugin 'type' defines where the plugin is loaded
// 0 = public       : only on the public side of the website (default)
// 1 = public+admin : on both the public and non-AJAX admin side
// 2 = library      : only when include_plugin() or require_plugin() is called
// 3 = admin        : only on the non-AJAX admin side
// 4 = admin+ajax   : only on admin side
// 5 = public+admin+ajax   : on both the public and admin side
$plugin['type'] = 1;

// Plugin 'flags' signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use.
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';


if (!defined('txpinterface'))
    @include_once('zem_tpl.php');

if (0) {
?>
# --- BEGIN PLUGIN HELP ---

# --- END PLUGIN HELP ---
<?php
}

# --- BEGIN PLUGIN CODE ---

upm_image_install();

	if (txpinterface == 'admin')
	{
		add_privs('upm_image_prefs', '1,2');
		register_tab('extensions', 'upm_image_prefs', 'upm_image');
		register_callback('upm_image_prefs', 'upm_image_prefs');
	}

	else
	{
		Txp::get('\Textpattern\Tag\Registry')
		 ->register('upm_image')
		 ->register('upm_img_thumb_width')
		 ->register('upm_img_full_url')
		 ->register('upm_img_alt')
		 ->register('upm_img_thumb_url')
		 ->register('upm_img_thumb_height')
		 ->register('upm_img_caption');

		if (gps('js') == 'upm_image')
		{
			while (@ob_end_clean());

			upm_image_js();
		}

		else
		{
			upm_image_check_load();
		}
	}

// ---

	function upm_article_image($atts = array(), $thing = NULL)
	{
		global $prefs, $thisarticle;

		$atts = lAtts(array(
			'break'		=> '',
			'break_class'	=> '',
			'class'		=> '',
			'form'		=> '',
			'id'		=> '',
			'limit'		=> '',
			'offset'	=> 0,
			'show_width'	=> 'yes',
			'show_height'	=> 'yes',
			'show_alt'	=> 'yes',
			'show_title'	=> 'yes',
			'type'		=> 'image',
			'url'		=> '',
			'wrapform'	=> '',
			'wraptag'	=> '',
			'xhtml'		=> 'yes'
		), $atts);

		extract($atts);

		$article_image = $thisarticle['article_image'];

		if (empty($article_image))
		{
			return;
		}

		$image_url = rawurlencode($article_image);

		if (file_exists($image_url))
		{
			$w = '';
			$h = '';

			if ($show_width or $show_height)
			{
				$fopen = get_cfg_var('allow_url_fopen') ? true : false;

				if ($fopen)
				{
					list($w, $h) = getimagesize($image_url);
				}
			}

			$out = '<img loading="lazy" src="'.$image_url.'"'.
				( ($id and !$wraptag) ? ' id="'.$id.'"' : '').
				( ($class and !$wraptag) ? ' class="'.$class.'"' : '').
				( ($w and ($show_width == 'yes')) ? ' width="'.$w.'"' : '' ).
				( ($h and ($show_height == 'yes')) ? ' height="'.$h.'"' : '' ).
				' alt=""'.
				($xhtml == 'yes' ? ' />' : '>');

			if ($url)
			{
				$out = is_numeric($url) ? href($out, permlinkurl_id($url)) :	href($out, $url);
			}

			return ($wraptag) ? doTag($out, $wraptag, $class, '', $id) : $out;
		}

		if (strstr($article_image, ','))
		{
			if ($limit == 1)
			{
				$article_image = join('', array_slice(explode(',', $article_image), $offset, 1));
			}

			else
			{
				return upm_article_image_list($article_image, $atts, $thing);
			}
		}

		if (is_numeric($article_image))
		{
			$image_id = doSlash($article_image);

			$rs = safe_row('SQL_NO_CACHE *', 'txp_image', "id = '$image_id' limit 0, 1");

			if (!$rs)
			{
				return ($prefs['production_status'] != 'live') ?
					upm_image_gTxt('not_found', array('{image}' => $image_id)) :
					'';
			}
		}

		else
		{
			$image_name = doSlash($article_image);

			$rs = safe_row('SQL_NO_CACHE *', 'txp_image', "name = '$image_name' limit 0, 1");

			if (!$rs)
			{
				return ($prefs['production_status'] != 'live') ?
					upm_image_gTxt('not_found', array('{image}' => $image_name)) :
					'';
			}
		}

		if ($form or $thing)
		{
			return upm_img_custom($rs, $atts, $form, $thing);
		}

		else
		{
			return upm_img_default($rs, $atts);
		}
	}

// ---
// Parts from zem_article_image
// http://thresholdstate.com/
// http://vigilant.tv/
// Thanks Alex.

	function upm_article_image_list($article_image, $atts, $thing = NULL)
	{
		global $prefs;

		extract($atts);

		$out = array();

		if ($limit)
		{
			$images = array_slice(explode(',', $article_image), $offset, $limit);
		}

		else
		{
			$images = array_slice(explode(',', $article_image), $offset);
		}

		for ($i = 0; $i < count($images); $i++)
		{
			if (empty($images[$i]))
			{
				unset($images[$i]);
			}
		}

		$ids_requested = $images;

		$images = doSlash(join(',', $images));

		$rs = safe_rows('SQL_NO_CACHE *', 'txp_image', "id in($images) order by field(id, $images)");

		if ($rs)
		{
			$ids_found = array();

			foreach ($rs as $row)
			{
				$ids_found[] = $row['id'];

				if ($form or $thing)
				{
					$out[] = upm_img_custom($row, $atts, $form, $thing);
				}

				else
				{
					$img_url = hu.$prefs['img_dir'].'/';
					$img = $row['id'].$row['ext'];

					if (in_array($type, array('popup', 'thumb', 'thumbnail')))
					{
						$img_path = $prefs['path_to_site'].DS.$prefs['img_dir'].DS;
						$thumb = $row['id'].'t'.$row['ext'];

						$thumb_exists = false;

						if ($row['thumbnail'] and file_exists($img_path.$thumb))
						{
							$thumb_exists = true;

							list($thumb_w, $thumb_h) = getimagesize($img_path.$thumb);
						}
					}

					switch ($type)
					{
						case 'popup':
							if ($thumb_exists)
							{
								$out[] = '<a href="'.$img_url.$img.'"'.
									( ($row['caption'] and ($show_title == 'yes')) ? ' title="'.htmlspecialchars($row['caption']).'"' : '' ).
									( ($class and !$wraptag) ? ' class="'.$class.'"' : '').
									' onclick="upm_pop_img(this.href, \''.$row['w'].'\', \''.$row['h'].'\', \''.$row['name'].'\', this.title); return false;">'.
									'<img loading="lazy" src="'.$img_url.$thumb.'"'.
									($show_width == 'yes' ? ' width="'.$thumb_w.'"' : '').
									($show_height == 'yes' ? ' height="'.$thumb_h.'"' : '').
									( ($row['alt'] and ($show_alt == 'yes')) ? ' alt="'.htmlspecialchars($row['alt']).'" title=""' : ' alt=""' ).
									($xhtml == 'yes' ? ' />' : '>').
									'</a>';
							}

							elseif ($prefs['production_status'] != 'live')
							{
								$out[] = upm_image_gTxt('no_thumb', array('{image}' => $row['id']));
							}
						break;

						case 'thumbnail':
						case 'thumb':
							if ($thumb_exists)
							{
								$out[] = '<img loading="lazy" src="'.$img_url.$thumb.'"'.
									( ($class and !$wraptag) ? ' class="'.$class.'"' : '').
									($show_width == 'yes' ? ' width="'.$thumb_w.'"' : '').
									($show_height == 'yes' ? ' height="'.$thumb_h.'"' : '').
									( ($row['alt'] and ($show_alt == 'yes')) ? ' alt="'.htmlspecialchars($row['alt']).'"' : ' alt=""').
									( ($row['caption'] and ($show_title == 'yes')) ? ' title="'.htmlspecialchars($row['caption']).'"' : '').
									($xhtml == 'yes' ? ' />' : '>');
							}

							elseif ($prefs['production_status'] != 'live')
							{
								$out[] = upm_image_gTxt('no_thumb', array('{image}' => $row['id']));
							}
						break;

						default:
							$temp = '<img loading="lazy" src="'.$img_url.$img.'"'.
								( ($class and !$wraptag) ? ' class="'.$class.'"' : '').
								($show_width == 'yes' ? ' width="'.$row['w'].'"' : '').
								($show_height == 'yes' ? ' height="'.$row['h'].'"' : '').
								( ($row['alt'] and ($show_alt == 'yes')) ? ' alt="'.htmlspecialchars($row['alt']).'"' : '' ).
								( ($row['caption'] and ($show_title == 'yes')) ? ' title="'.htmlspecialchars($row['caption']).'"' : '' ).
								($xhtml == 'yes' ? ' />' : '>');

							if ($url)
							{
								$temp = is_numeric($url) ?
									href($temp, permlinkurl_id($url)) :
									href($temp, $url);
							}

							$out[] = $temp;
						break;
					}
				}
			}

			$ids_requested = array_unique($ids_requested);

			if ($wrapform)
			{
				$upm_img_custom['list'] = join('', $out);

				$temp = parse(fetch_form($wrapform));
			}

			else
			{
				$atts = ($id) ? ' id="'.$id.'"' : '';

				$temp = doWrap($out, $wraptag, $break, $class, $break_class, $atts);
			}

			if ($prefs['production_status'] != 'live' && ($ids_requested != $ids_found))
			{
				$ids_missing = array_diff($ids_requested, $ids_found);

				return $temp.upm_image_gTxt('not_found_list', array('{list}' => join(', ', $ids_missing)));
			}

			else
			{
				return $temp;
			}
		}

		return ($prefs['production_status'] != 'live') ?
			upm_image_gTxt('not_found_list', array('{list}' => join(', ', $images)))
			: '';
	}

// ---
// if one or more article images exist

	function upm_if_article_image($atts = array(), $thing = NULL)
	{
		global $thisarticle;

		assert_article();

		$condition = ( !empty($thisarticle['article_image']) );

		return parse(EvalElse($thing, $condition));
	}

// ---
// if more than one article image exists

	function upm_if_article_image_list($atts = array(), $thing = NULL)
	{
		global $thisarticle;

		assert_article();

		$condition = ( !empty($thisarticle['article_image']) and strstr($thisarticle['article_image'], ',') );

		return parse(EvalElse($thing, $condition));
	}

// ---

	function upm_image($atts = array(), $thing = NULL)
	{
		global $prefs;

		$atts = lAtts(array(
			'class'		=> '',
			'form'		=> '',
			'id'		=> '',
			'image_id'	=> '',
			'image_name'	=> '',
			'image_url'	=> '',
			'show_width'	=> 'yes',
			'show_height'	=> 'yes',
			'show_alt'	=> 'yes',
			'show_title'	=> 'yes',
			'type'		=> 'image',
			'url'		=> '',
			'wraptag'	=> '',
			'xhtml'		=> 'yes'
		), $atts);

		extract($atts);

		if ($type == 'article')
		{
			return upm_article_image($atts, $thing);
		}

		if ($image_url)
		{
			$image_url = rawurlencode($image_url);

			if (file_exists($image_url))
			{
				$w = '';
				$h = '';

				$fopen = get_cfg_var('allow_url_fopen') ? true : false;

				if ($fopen)
				{
					list($w, $h) = getimagesize($image_url);
				}

				$out = '<img loading="lazy" src="'.$image_url.'"'.
					( ($id and !$wraptag) ? ' id="'.$id.'"' : '').
					( ($class and !$wraptag) ? ' class="'.$class.'"' : '').
					( ($w and ($show_width == 'yes')) ? ' width="'.$w.'"' : '' ).
					( ($h and ($show_height == 'yes')) ? ' height="'.$h.'"' : '' ).
					' alt=""'.
					($xhtml == 'yes' ? ' />' : '>');

				if ($url)
				{
					$out = is_numeric($url) ?
						href($out, permlinkurl_id($url)) :
						href($out, $url);
				}

				return ($wraptag) ? doTag($out, $wraptag, $class, '', $id) : $out;
			}

			return ($prefs['production_status'] != 'live') ?
				upm_image_gTxt('not_found', array('{image}' => $image_url)) :
				'';
		}

		elseif ($image_id)
		{
			if (!is_numeric($image_id))
			{
				return upm_image_gTxt('invalid_id');
			}

			$image_id = doSlash($image_id);

			$rs = safe_row('SQL_NO_CACHE *', 'txp_image', "id = '$image_id' limit 0, 1");

			if (!$rs)
			{
				return ($prefs['production_status'] != 'live') ?
					upm_image_gTxt('not_found', array('{image}' => $image_id)) :
					'';
			}
		}

		elseif ($image_name != '')
		{
			$image_name = doSlash($image_name);

			$rs = safe_row('SQL_NO_CACHE *', 'txp_image', "name = '$image_name' limit 0, 1");

			if (!$rs)
			{
				return ($prefs['production_status'] != 'live') ?
					upm_image_gTxt('not_found', array('{image}' => $image_name)) :
					'';
			}
		}

		else
		{
			return ($prefs['production_status'] != 'live') ?
				upm_image_gTxt('nothing_requested') :
				'';
		}

		if ($form or $thing)
		{
			return upm_img_custom($rs, $atts, $form, $thing);
		}

		else
		{
			return upm_img_default($rs, $atts);
		}
	}

// ---

	function upm_img_custom($row, $atts, $form = '', $thing = NULL)
	{
		global $prefs, $upm_img_custom;

		if ($form)
		{
			$what = fetch_form($form);
		}

		elseif ($thing)
		{
			$what = $thing;
		}

		else
		{
			return;
		}

		$img_url = hu.$prefs['img_dir'].'/';
		$img_path = $prefs['path_to_site'].DS.$prefs['img_dir'].DS;

		$upm_img_custom = array(
			'alt'		=> $row['alt'],
			'author'	=> $row['author'],
			'caption'	=> $row['caption'],
			'category'	=> $row['category'],
			'date'		=> $row['date'],
			'full_height'	=> $row['h'],
			'full_url'	=> $img_url.$row['id'].$row['ext'],
			'full_width'	=> $row['w'],
			'href'		=> $atts['url'],
			'id'		=> $row['id'],
			'list'		=> '',
			'name'		=> $row['name'],
			'thumb_url'	=> '',
			'thumb_height'	=> '',
			'thumb_width'	=> '',
		);

		$thumb = $row['id'].'t'.$row['ext'];

		if ($row['thumbnail'] and file_exists($img_path.$thumb))
		{
			list($w, $h) = getimagesize($img_path.$thumb);

			$upm_img_custom['thumb_height'] = $h;
			$upm_img_custom['thumb_url']		= $img_url.$thumb;
			$upm_img_custom['thumb_width']	= $w;
		}

		return parse($what);
	}

// ---

	function upm_img_custom_assert()
	{
		global $upm_img_custom;

		if (empty($upm_img_custom))
		{
			trigger_error(upm_image_gTxt('error_context'));
		}
	}

// ---

	function upm_img_alt($atts = array())
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		extract(lAtts(array(
			'escape' => 'html'
		), $atts));

		return ($escape == 'html') ?
			htmlspecialchars($upm_img_custom['alt']) :
			$upm_img_custom['alt'];
	}

// ---

	function upm_img_author($atts = array())
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		return get_author_name($thisarticle['authorid']);
	}

// ---

	function upm_img_caption($atts = array())
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		extract(lAtts(array(
			'class'		=> '',
			'escape'	=> 'html',
			'label'		=> '',
			'labeltag'	=> '',
			'wraptag'	=> ''
		), $atts));

		if ($upm_img_custom['caption'])
		{
			$caption = ($escape == 'html') ?
				htmlspecialchars($upm_img_custom['caption']) :
				$upm_img_custom['caption'];

			return doLabel($label, $labeltag).doTag($caption, $wraptag, $class);
		}
	}

// ---

	function upm_img_category($atts = array())
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		extract(lAtts(array(
			'class'		=> '',
			'label'		=> '',
			'labeltag'	=> '',
			'title'		=> '0',
			'wraptag'	=> '',
		), $atts));

		if ($upm_img_custom['category'])
		{
			$category = ($title) ?
				fetch_category_title($upm_img_custom['category'], 'image') :
				$upm_img_custom['category'];

			return doLabel($label, $labeltag).doTag($category, $wraptag, $class);
		}
	}

// ---

	function upm_img_date($atts = array())
	{
		global $upm_img_custom, $prefs;

		upm_img_custom_assert();

		extract(lAtts(array(
			'format'	=> $prefs['dateformat'],
			'gmt'		=> '',
			'lang'		=> '',
		), $atts));

		if ($format)
		{
			return safe_strftime($format, $upm_img_custom['date'], $gmt, $lang);
		}
	}

// ---

	function upm_img_full_height($atts = array())
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		return $upm_img_custom['full_height'];
	}

// ---

	function upm_img_full_url($atts = array())
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		return $upm_img_custom['full_url'];
	}

// ---

	function upm_img_full_width($atts = array())
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		return $upm_img_custom['full_width'];
	}

// ---

	function upm_img_href($atts = array(), $thing = NULL)
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		extract(lAtts(array(
			'class'		=> '',
			'escape'	=> '',
			'label'		=> '',
			'labeltag'	=> '',
			'wraptag'	=> '',
		), $atts));

		if ($upm_img_custom['href'])
		{
			$href = ($escape == 'html') ?
				htmlspecialchars($upm_img_custom['href']) :
				$upm_img_custom['href'];

			$href = ($thing) ? href(parse($thing), $href) : $href;

			return doLabel($label, $labeltag).doTag($href, $wraptag, $class);
		}
	}

// ---

	function upm_img_id($atts = array())
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		return $upm_img_custom['id'];
	}

// ---

	function upm_img_list($atts = array())
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		return $upm_img_custom['list'];
	}

// ---

	function upm_img_name($atts = array())
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		return $upm_img_custom['name'];
	}

// ---

	function upm_img_thumb_height($atts = array())
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		return $upm_img_custom['thumb_height'];
	}

// ---

	function upm_img_thumb_url($atts = array())
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		return $upm_img_custom['thumb_url'];
	}

// ---

	function upm_img_thumb_width($atts = array())
	{
		global $upm_img_custom;

		upm_img_custom_assert();

		return $upm_img_custom['thumb_width'];
	}

// ---

	function upm_img_default($row, $atts)
	{
		global $prefs;

		extract($atts);

		$img_url = hu.$prefs['img_dir'].'/';
		$img = $row['id'].$row['ext'];

		if (in_array($type, array('popup', 'thumb', 'thumbnail')))
		{
			$img_path = $prefs['path_to_site'].DS.$prefs['img_dir'].DS;
			$thumb = $row['id'].'t'.$row['ext'];

			$thumb_exists = false;

			if ($row['thumbnail'] and file_exists($img_path.$thumb))
			{
				$thumb_exists = true;

				list($thumb_w, $thumb_h) = getimagesize($img_path.$thumb);
			}
		}

		switch ($type)
		{
			case 'popup':
				if ($thumb_exists)
				{
					$out = '<a href="'.$img_url.$img.'"'.
						( ($id and !$wraptag) ? ' id="'.$id.'"' : '').
						( ($class and !$wraptag) ? ' class="'.$class.'"' : '').
						( ($row['caption'] and ($show_title == 'yes')) ? ' title="'.htmlspecialchars($row['caption']).'"' : '' ).
						' onclick="upm_pop_img(this.href, '.chr(39).$row['w'].chr(39).', '.chr(39).$row['h'].chr(39).', '.chr(39).$row['name'].chr(39).', this.title); return false;">'.
						'<img loading="lazy" src="'.$img_url.$thumb.'"'.
						($show_width == 'yes' ? ' width="'.$thumb_w.'"' : '').
						($show_height == 'yes' ? ' height="'.$thumb_h.'"' : '').
						( ($row['alt'] and ($show_alt == 'yes')) ? ' alt="'.htmlspecialchars($row['alt']).'" title=""' : ' alt=""' ).
						($xhtml == 'yes' ? ' />' : '>').
						'</a>';

					return ($wraptag) ? doTag($out, $wraptag, $class, '', $id) : $out;
				}

				elseif ($prefs['production_status'] != 'live')
				{
					return upm_image_gTxt('no_thumb', array('{image}' => $row['id']));
				}
			break;

			case 'thumbnail':
			case 'thumb':
				if ($thumb_exists)
				{
					$out = '<img loading="lazy" src="'.$img_url.$thumb.'"'.
						( ($id and !$wraptag) ? ' id="'.$id.'"' : '').
						( ($class and !$wraptag) ? ' class="'.$class.'"' : '').
						($show_width == 'yes' ? ' width="'.$thumb_w.'"' : '').
						($show_height == 'yes' ? ' height="'.$thumb_h.'"' : '').
						( ($row['alt'] and ($show_alt == 'yes')) ? ' alt="'.htmlspecialchars($row['alt']).'"' : ' alt=""' ).
						( ($row['caption'] and ($show_title == 'yes')) ? ' title="'.htmlspecialchars($row['caption']).'"' : '' ).
						($xhtml == 'yes' ? ' />' : '>');

					if ($url)
					{
						$out = is_numeric($url) ? href($out, permlinkurl_id($url)) : href($out, $url);
					}

					return ($wraptag) ? doTag($out, $wraptag, $class, '', $id) : $out;
				}

				elseif ($prefs['production_status'] != 'live')
				{
					return upm_image_gTxt('no_thumb', array('{image}' => $row['id']));
				}
			break;

			case 'image':
			default:
				$out = '<img loading="lazy" src="'.$img_url.$img.'"'.
					( ($id and !$wraptag) ? ' id="'.$id.'"' : '').
					( ($class and !$wraptag) ? ' class="'.$class.'"' : '').
					($show_width == 'yes' ? ' width="'.$row['w'].'"' : '').
					($show_height == 'yes' ? ' height="'.$row['h'].'"' : '').
					( ($row['alt'] and ($show_alt == 'yes')) ? ' alt="'.htmlspecialchars($row['alt']).'"' : ' alt=""' ).
					( ($row['caption'] and ($show_title == 'yes')) ? ' title="'.htmlspecialchars($row['caption']).'"' : '' ).
					($xhtml == 'yes' ? ' />' : '>');

				if ($url)
				{
					$out = is_numeric($url) ? href($out, permlinkurl_id($url)) : href($out, $url);
				}

				return ($wraptag) ? doTag($out, $wraptag, $class, '', $id) : $out;
			break;
		}
	}

// ---

	function upm_image_check_load()
	{
		global $prefs;

		if ($prefs['upm_image_load_script'] == 1)
		{
			ob_start('upm_image_link');
		}
	}

// ---

	function upm_image_link($buffer)
	{
		$find = '</head>';
		$replace = n.t.'<script async type="text/javascript" src="'.hu.'index.php?js=upm_image"></script>'.n.$find;

		return str_replace($find, $replace, $buffer);
	}

// ---

	function upm_image_js()
	{
		header("Content-type: text/javascript");

		echo <<<js
function upm_pop_img(src, w, h, name, title)
{
	var ww = parseInt(w);
	var wh = parseInt(h);

	if (ww < 100)
	{
		ww = 125;
	}

	if (wh < 100)
	{
		wh = 125;

		ww += 75;
	}

	var scroll = false;

	if (screen.width && (screen.width < ww))
	{
		scroll = 'yes';
		ww = screen.width;
	}

	if (screen.height && (screen.height < wh))
	{
		scroll = 'yes';
		wh = screen.height;
	}

	if (!title)
	{
		title = name;
	}

	var t = (screen.height) ? (screen.height - wh) / 2 : 0;
	var l = (screen.width) ? (screen.width - ww) / 2 : 0;

	var upm_pop_win = window.open('', 'upm_pop_win', 'top = '+t+', left = '+l+', width = '+ww+', height = '+wh+', toolbar = no, location = no, directories = no, status = no, menubar = no, scrollbars = '+scroll+', copyhistory = no, resizable = yes');

	upm_pop_win.document.writeln('<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">');
	upm_pop_win.document.writeln('<html>');
	upm_pop_win.document.writeln('<head>');
	upm_pop_win.document.writeln('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">');
	upm_pop_win.document.writeln('<meta http-equiv="imagetoolbar" content="no">');
	upm_pop_win.document.writeln('<title>'+title+'</title>');
	upm_pop_win.document.writeln('<style type="text/css">');
	upm_pop_win.document.writeln('<!--');
	upm_pop_win.document.writeln('body {');
	upm_pop_win.document.writeln('margin: 0;');
	upm_pop_win.document.writeln('padding: 0;');
	upm_pop_win.document.writeln('color: #000;');
	upm_pop_win.document.writeln('background-color: #fff;');
	upm_pop_win.document.writeln('text-align: center;');

	if (scroll == false)
	{
		upm_pop_win.document.writeln('overflow: hidden;');
	}

	upm_pop_win.document.writeln('}');

	upm_pop_win.document.writeln('');
	upm_pop_win.document.writeln('img {');
	upm_pop_win.document.writeln('margin: 0 auto;');
	upm_pop_win.document.writeln('padding: 0;');
	upm_pop_win.document.writeln('border: none;');

	if (wh == 125)
	{
		upm_pop_win.document.writeln('margin-top: ' + Math.floor(50-(h/2)) + 'px;');
	}

	upm_pop_win.document.writeln('}');

	upm_pop_win.document.writeln('-->');
	upm_pop_win.document.writeln('</style>');
	upm_pop_win.document.writeln('</head>');
	upm_pop_win.document.writeln('<body>');
	upm_pop_win.document.writeln('<div id="upm-image-view">');
	upm_pop_win.document.writeln('<img src="'+src+'" width="'+w+'" height="'+h+'" alt="">');
	upm_pop_win.document.writeln('</div>');
	upm_pop_win.document.writeln('</body>');
	upm_pop_win.document.write('</html>');

	upm_pop_win.document.close();

	upm_pop_win.focus();

	return false;
}
js;

		exit(0);
	}

// ---

	function upm_image_prefs($event, $step)
	{
		global $prefs;

		pagetop(upm_image_gTxt('prefs'), ($step == 'update' ? gTxt('preferences_saved') : ''));

		if ($step == 'update')
		{
			$load_script = doSlash(ps('load_script'));

			safe_update('txp_prefs', "val = '$load_script'", "name = 'upm_image_load_script'");

			$prefs = get_prefs();
		}

		echo n.n.'<div style="margin: 3em auto auto auto; width: 16em;">'.

			n.n.hed(upm_image_gTxt('prefs'), '1').

			n.n.form(
				n.eInput('upm_image_prefs').
				n.sInput('update').

				n.n.graf(
					upm_image_gTxt('load_script').br.
					n.yesnoRadio('load_script', $prefs['upm_image_load_script'])
				).

				n.n.fInput('submit', 'update', 'Update', 'smallerbox')
			).n.n.'</div>';
	}

// ---

	function upm_image_install()
	{
		global $prefs;

		if (!isset($prefs['upm_image_load_script']))
		{
			safe_insert('txp_prefs', "
				name = 'upm_image_load_script',
				val = '1',
				html = 'yesnoradio',
				prefs_id = 1,
				type = 2,
				event = 'admin',
				position = 0
			");

			$GLOBALS['prefs'] = get_prefs();
		}
	}

// ---

	function upm_image_gTxt($what, $atts = array())
	{
		$lang = array(
			'error_context'		=> 'upm_image: invalid context, tag must be used within the tag as a wrapper or from its own form called by the tag.',
			'invalid_id'		=> 'upm_image: supplied image id is invalid, it must be a single numeric value.',
			'load_script'		=> 'Auto-load JavaScript?',
			'no_thumb'		=> 'upm_image: image &#8220;{image}&#8221; does not have a thumbnail.',
			'not_found'		=> 'upm_image: requested image could not be found: &#8220;{image}&#8221;',
			'not_found_list'	=> 'upm_image: requested images could not be found: &#8220;{list}&#8221;',
			'nothing_requested'	=> 'upm_image: no image was requested.',
			'prefs'			=> 'upm_image Preferences'
		);

		return strtr($lang[$what], $atts);
	}

# --- END PLUGIN CODE ---
?>