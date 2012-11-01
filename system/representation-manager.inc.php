<?php
/*
 * See the NOTICE file distributed with this work for information
 * regarding copyright ownership.  QUT licenses this file to you
 * under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */


class RepresentationManager {

	private static $list = array();

### DEBUG
public static function dump() {
	print_r(self::$list);
}
public static function htmldump() {
	echo <<<HTML
<!doctype html>
<html lang="en">
<head>
<title>Debug: Representers</title>
<style type="text/css">
table,tr,th,td{vertical-align:top;border-collapse:collapse;}
tr:nth-child(even){background-color:#d8d8d8}
tr.big,tr.big th,tr.big td{border-top:1px solid black;}
table{border-bottom:1px solid black;}
tr.top th{background-color:#fff;}
th,td{padding:0 1em;}
</style>
</head>
<body>
<h1><a href="/debug/">Debug</a>: Representers</h1>
<table>
<tr class="top"><th>Rep. Class</th><th>Model</th><th>Type</th><th>QS</th></tr>
HTML;
	foreach (self::$list as $r) {
		$a = $r->list_types();
		$n=0;foreach($a as $t=>$q)$n++;
		echo '<tr class="big">
<th>' . htmlspecialchars(get_class($r)) . '</th>
';
		$first = true;
		foreach ($a as $t=>$q) {
			if (!$first) {
				echo "<td></td>";
			}
			if ($first) {
				echo '<td rowspan="'.$n.'">';
				if ($r instanceof BasicRepresenter){
					$ev = var_export($r,1);
					$ev = preg_replace('/(\S+)::__set_state\(/U', '(', $ev);
					$ev = eval('return '.$ev.';');
					if ($ev['all_models']) {
						echo '<code>*</code>';
					} else {
						foreach ($ev['model_types'] as $m=>$x) {
							if ($x) echo '<div><code>'.$m.'</code></div>';
							else echo '<div><code>! '.$m.'</code></div>';
						}
						foreach ($ev['model_classes'] as $m) {
							echo '<div><code>object:'.$m.'</code></div>';
						}
					}
#var_dump($ev['all_models'], $ev['model_types'], $ev['model_classes']);
#var_dump($ev);
				} else {
					echo "?";
				}
				echo "</td>\n";
				$first = false;
			}
			echo '<td><code>' . htmlspecialchars($t)."</code></td>\n";
			echo '<td>'.sprintf('%0.3f', intval($q*1000)/1000.0)."</td>\n";
		echo "</tr>\n";
		}
	}
	echo <<<HTML
</table>
<p>(Order is significant)</p>
</body>
</html>
HTML;
}
public static function dumpClass($klass) {
	foreach (self::$list as $rep) {
		if ($rep instanceof $klass) {
			var_dump($rep);
			echo "\n";
		}
	}
}
### /DEBUG

	public static function add($rep) {
		if (!($rep instanceof Representer)) {
			throw new Exception("not a Representer (" . get_class($rep) .")");
		}
		self::$list[] = $rep;
	}

	public static function represent($model) {
		$accepted_types = Request::content_types();
		if (!$accepted_types) {
			// if the client didn't specify anything, we have
			// to assume they will accept everything.
			$accepted_types = array(
				1000 => array(
					//array('option'=>'*/*','raw'=>'*/*' ),
					'*',
				)
			);
		}
		$accepted_charsets = Request::charsets();
		if (!$accepted_charsets) {
			$accepted_charsets = array( 1000 => array('*') );
		}
		$accepted_languages = Request::languages();
		if (!$accepted_languages) {
			$accepted_languages = array( 1000 => array('*') );
		}

		$candidate_reps = array();
		$best_rep = NULL;
		$best_type = NULL;
		$best_charset = NULL;
		$best_language = NULL;
		$best_qvalue = 0;
		foreach (self::$list as $rep) {
			if ($rep->can_do_model($model)) {
				$candidate_reps[] = $rep;
				$array = $rep->pick_best($accepted_types, $accepted_charsets, $accepted_languages);
				$prod = 1;
				foreach (array('type','charset','language') as $key) {
					if ($array[$key]) $prod *= ($array[$key]['weight'] / 10000.0);
					else $prod = 0;
				}
				if ($prod > $best_qvalue) {
					$best_rep = $rep;
					$best_type = $array['type']['type'];
					$best_charset = $array['charset']['charset'];
					$best_language = $array['language']['language'];
					$best_qvalue = $prod;
				}
			}
		}

		if ($best_rep) {
			$response = new Response(Request::http_version());
			$best_rep->represent($model, $best_type, $best_charset, $best_language, $response);
		} else {
			// urgh.. build up a nice response
			$response = self::generate406( Request::uri(), $candidate_reps );
		}
		return $response;
	}

	protected static function generate406($uri, $reps) {
		$array = array();
		foreach ($reps as $rep) {
			foreach ($rep->list_types() as $type => $qs) {
				if (!isset($array[$type]) || $array[$type] < $qs) {
					$array[$type] = $qs;
				}
			}
		}

		$response = new Response(NULL, 406);
		if (count($array) > 0) {
			$alts = array();
			$html = array();
			foreach ($array as $type => $qs) {
				// NOTE: we're not playing fair according to RFC2295 because we're
				// putting fragment identifiers in the variant URIs.  For some reason
				// they insist on these silly 'neighboring variant's
				$alts[] = sprintf('{"%s#%s" %0.3f {type %s}}', $uri, $type, $qs, $type);
				$html[] = sprintf('<li><code>%s</code> [%0.3f]</li>', htmlspecialchars($type), $qs);
			}
			$alts = implode(', ', $alts);
			$html = implode('', $html);
			$response->header('Vary', 'negotiate, accept')
				->header('TCN', 'list')
				->header('Alternates', $alts)
				->content_type('text/html; charset=iso-8859-1')
				->body( Response::generate_html('Not Acceptable', <<<HTML
    <p>The resource you requested could not be delivered in an acceptable format.</p>
    <p>Supported formats are:</p>
    <ul>$html</ul>
HTML
			));
		}

		return $response;
	}

	private function __construct() {}
	private function __clone() {}

}

