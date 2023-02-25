<?php
$plugin['version'] = '1.0';
$plugin['author'] = 'Robert Wetzlmayr';
$plugin['author_uri'] = 'http://awasteofwords.com/article/wet_peex-peek-into-textpattern-using-xml';
$plugin['description'] = 'Peek into Textpattern using XML';
$plugin['type'] = 3;

if (!defined('txpinterface'))
	@include_once('zem_tpl.php');

if (0) {
?>
# --- BEGIN PLUGIN HELP ---

h3. Peek into Textpattern using XML

*wet_peex* peeks for Textpattern objects as XML(Extensible Markup Language) resources.

h4. usage:

*wet_peex* is a backend plugin without any user interface. It handles HTTP GET requests and replies with an XML response.

To query @wet_peex@, send a request to the resource located at @http://yoursite.tld/textpattern?wet_peex@.

wet_peex understands these query parameters:

* @object_type@: @article@, @section@, @category@, @link@, @image@, @file@. Use one of these as a value for the @wet_peex@ GET parameter, e.g. @http://yoursite.tld/textpattern?wet_peex=article@
* @sort@: Sort criterion
* @dir@: Sort direction. Use either @asc@ for ascending sort order, or @desc@ for descending sort order
* @search@: Textual search criterion
* @offset@: First object's ordinal in reply
* @limit@: Maximum objects returned

A sample request:

@http://yoursite.tld/textpattern?wet_peex=article&sort=posted&dir=asc@

The XML reply message is rather self explanatory. View source for details. @article@ is the only currently implemented object type.

h4. Licence and Disclaimer

This plug-in is released under the "Gnu General Public Licence":http://www.gnu.org/licenses/gpl.txt.

# --- END PLUGIN HELP ---

<?php
}

# --- BEGIN PLUGIN CODE ---

if (@txpinterface == 'admin') {
	$what = gps('wet_peex');
	if (in_array($what, array('article', 'section', 'category', 'link', 'image', 'file'))) {
		wet_peex($what);
		exit();
	} elseif(!empty($what)) {
		// curiosity killed the cat
		header('HTTP/1.1 400 Bad request');
		header('Status: 400 Bad request');
		exit();
	}
}

function wet_peex($what)
{
	// get and sanitize some common parameters
	extract(gpsa(array('limit', 'offset', 'sort', 'dir', 'crit', 'search')));

	$limit = intval(doSlash($limit));
	if (0 == $limit) {
		$limit = 20;
	}

	$offset = intval(doSlash($offset));

	if ($dir != 'desc') {
		$dir = 'asc';
	}

	// preludium
	while(@ob_end_clean()); // eat previous output to assert well-formed XML
	header('Content-Type: text/xml; charset=utf-8');
	header('Expires: '.date('r', time()+60)); // avoid too frequent updates
	header('Cache-Control: private');
	echo '<?xml version="1.0" encoding="utf-8" ?>'.n;
	echo '<textpattern>'.n;

	switch($what) {
		case 'article':
			if (empty($search)) {
				$search = '1=1';
			} else {
				$search_escaped = doSlash($search);
				$search = "Title rlike '$search_escaped' or Body rlike '$search_escaped'";
			}

			switch ($sort)
			{
				case 'id':
					$sort_sql = 'ID '.$dir;
				break;

				case 'posted':
					$sort_sql = 'Posted '.$dir;
				break;

				case 'lastmod':
					$sort_sql = 'LastMod '.$dir;
				break;

				case 'title':
					$sort_sql = 'Title '.$dir.', Posted desc';
				break;

				case 'section':
					$sort_sql = 'Section '.$dir.', Posted desc';
				break;

				case 'category1':
					$sort_sql = 'Category1 '.$dir.', Posted desc';
				break;

				case 'category2':
					$sort_sql = 'Category2 '.$dir.', Posted desc';
				break;

				case 'status':
					$sort_sql = 'Status '.$dir.', Posted desc';
				break;

				case 'author':
					$sort_sql = 'AuthorID '.$dir.', Posted desc';
				break;

				case 'comments':
					$sort_sql = 'comments_count '.$dir.', Posted desc';
				break;

				default:
					$sort_sql = 'Posted '.$dir;
				break;
			}

			$count = safe_count('textpattern', $search); // ignore article status
			$articles = safe_rows('*, unix_timestamp(Posted) as Posted', 'textpattern', "$search order by $sort_sql limit $limit offset $offset");

			require_once txpath.'/publish/taghandlers.php';

			echo "<articles count='$count' offset='$offset' limit='$limit'>".n;
			foreach ($articles as $a) {
				$teaser = htmlspecialchars
							(preg_replace('/^(.{0,45}).*$/su','$1',
								trim(strip_tags($a['Body_html']))
							));
				echo '<article>'.n.
					t.'<id>'.$a['ID'].'</id>'.n.
					t.'<title>'.escape_title($a['Title']).'</title>'.n.
					t.'<section>'.htmlspecialchars($a['Section']).'</section>'.n;
					if(!empty($a['Category1']) || !empty($a['Category2'])) {
						echo t.'<categories>'.n.
							t.t.'<category level="1">'.htmlspecialchars($a['Category1']).'</category>'.n.
							t.t.'<category level="2">'.htmlspecialchars($a['Category2']).'</category>'.n.
						t.'</categories>'.n;
					}
					echo t.'<posted>'.date('Y-m-d H:i:s', $a['Posted']).'</posted>'.n.
					t.'<teaser>'.$teaser.'</teaser>'.n.
					t.'<lastmod>'.$a['LastMod'].'</lastmod>'.n.
					t.'<permlink>'.permlinkurl($a).'</permlink>'.n.
					'</article>'.n;
			}
			echo '</articles>';

			break; // $what == 'article'
		/**
		 * @todo Implement for other txp objects
		 */
		case 'section':
		case 'category':
		case 'image';
		case 'file';
			echo '<message>not implemented</message>';
			break;
		default:
			break;
	}

	// postludium
	echo n.'</textpattern>';
}

# --- END PLUGIN CODE ---

?>
