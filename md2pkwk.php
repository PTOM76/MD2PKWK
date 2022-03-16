#!/usr/local/bin/php -q
<?php
// Markdown方言: GitHub Flavored Markdown

if (!isset($argv)) {
	echo "無効な引数です。CLIから引数(ファイル名)を指定して実行してください。";
	exit;
}
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    system('cls');
    echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
} else {
    system('clear');
}
echo "ファイル名: " . $argv[1];

$contents = file_get_contents($argv[1]);
echo "MarkdownからPukiWiki記法へファイルを変換しています...\n";
$newContents = md2pkwk($contents);
echo "ファイルの変換が完了しました。\n";
file_put_contents(pathinfo($argv[1], PATHINFO_FILENAME) . '.txt', $newContents);

function md2pkwk($str) {
	$newLines = array();
	$instance = new Md2Pkwk();
	
	$str = str_replace(array("\r\n","\r"), "\n", $str);
	$lines = explode("\n", $str);
	
	foreach ($lines as $line) {
		$newLine = $instance -> convert($line);
		if ($newLine !== false) {
			$newLines[] = $newLine;
		}
	}
	$newStr = implode("\n", $newLines);
	return $newStr;
}

class Md2Pkwk {
	public $listLevel = 0;
	public $listChars = array();
	public $multiPlugin = 0;

	public $multiPre = false;
	public $list = false;
	public $listChar = '';
	
	public $tableDataCell = false;
	public $tableCellHead = array();
	
	public function __construct() {
	
	}
	
	public function Md2Pkwk() {
		$this->__construct();
	}
	
	public function convert($line) {
		if (!isset($line[0])) return $line;
		$first = $line[0];
		$end = substr($line, -1);
		
		//if ($first == " ") if ($line[3] != " ") $first == ltrim($line)[0];

		$head = '';
		$body = '';
		$foot = '';
		
		$len = strlen($line);
		// -ブロック
		
		// 見出し
		if ($first == '#') {
			$level = min(6, strspn($line, '#'));
			$newlevel = min(3, $level);
			//echo substr($line, $level + 1, 1);
			if (substr($line, $level, 1) == " ") {
				$head = str_repeat('*', $newlevel);
				$body = substr($line, $level);
			} else {
				$body = $line;
			}
		} else
		
		// 引用文
		if ($first == '>') {
			preg_match("/((\s*?>\s*?)+)/", $line, $m);
			//echo $m[1] . "\n";
			$level = strspn(str_replace(' ', '', $m[1]), '>');
			$newlevel = min(3, $level);
			$head = str_repeat('>', $newlevel);
			$body = substr($line, strlen($m[1]));
		} else
		
		// 水平線
		if (($first == '-' || $first == '_') && $len >= 3 && $line == str_repeat($first, $len)) {
			if ($len == 3) $len = 4;
			$line = str_repeat('-', $len);
			return $line;
		} else
		
		// 整形済みテキスト (空白)
		if ($first == ' ' && !$this->list) {
			$space = strspn($line, ' ');
			if ($space >= 4) {
				$line = ' ' . substr($line, 4);
				return $line;
			} else {
				return $this->convert(ltrim($line));
			}
		} else
		
		// 整形済みテキスト (タブ)
		if ($first == "\t") {
			$line = ' ' . substr($line, 1);
			return $line;
		} else
		
		// 整形済みテキスト (複数行)
		if ($first == "`" || $this->multiPre) {
			if (preg_match("/^```.*?$/", $line)) {
				$this->multiPre = !$this->multiPre;
				return false;
			}
			if ($this->multiPre) {
				$line = ' ' . $line;
				return $line;
			}
			$body = $line;
		} else
		
		// 表テーブル
		if ($first == '|' && preg_match("/^\s*?\|(.*?)\|\s*?$/", $line, $m)) {
			$body = $line;
			if (!$this->tableDataCell) {
				$foot = "h";
				$cells = explode('|', $m[1]);
				$table_separator = true;
				$index = 0;
				foreach ($cells as $cell) {
					$sep = false;
					// |---|
					if (preg_match("/\s*?-+?\s*?/", $cell)) {
						$this->tableCellHead[$index] = "";
						$sep = true;
					}
					// |:---:|
					if (preg_match("/\s*?:-+?:\s*?/", $cell)) {
						$this->tableCellHead[$index] = "CENTER:";
						$sep = true;
					}
					// |:---|
					if (preg_match("/\s*?:-+?\s*?/", $cell)) {
						$this->tableCellHead[$index] = "LEFT:";
						$sep = true;
					}
					// |---:|
					if (preg_match("/\s*?-+?:\s*?/", $cell)) {
						$this->tableCellHead[$index] = "RIGHT:";
						$sep = true;
					}
					if (!$sep)
						$table_separator = false;
					++$index;
				}
				if ($table_separator) {
					$this->tableDataCell = true;
					return false;
				}
			} else {
				$cells = explode('|', $m[1]);
				$index = 0;
				$newCells = array();
				foreach ($cells as $cell) {
					if (isset($this->tableCellHead[$index]))
						$cell = $this->tableCellHead[$index] . $cell;
					++$index;
					$newCells[] = $cell;
				}
				$body = "|" . implode('|', $newCells) . "|";
			}
		} else
		
		// リスト
		if (preg_match("/^(\s*?)(-|\+|\*) (.*?)$/", $line, $m)) {
			$this->list = true;
			$space = $m[1];
			$char = $m[2];
			$text = $m[3];
			
			$head = '-' . trim(str_replace('  ', '-', $space));
			$body = " " . $text . ($this->listChar != $char && $this->listChar != '' ? "\n" : '');
			$this->listChar = $char;
		} else {
			$this->list = false;
			$this->tableDataCell = false;
			$this->tableCellHead = array();
			$body = $line;
		}
		
		// -インライン
		
		// 太字
		$body = preg_replace("/\*\*(.+?)\*\*/", "''$1''", $body);
		$body = preg_replace("/__(.+?)__/", "''$1''", $body);
		
		// イタリック
		$body = preg_replace("/\*(.+?)\*/", "'''$1'''", $body);
		$body = preg_replace("/_(.+?)_/", "'''$1'''", $body);
		
		// 打消し
		$body = preg_replace("/\~\~(.+?)\~\~/", "%%$1%%", $body);
		
		// 画像
		$body = preg_replace("/!\[(.+?)\]\((.+?)\)/", "&ref($2,$1);", $body);
		
		// リンク
		$body = preg_replace("/\[(.+?)\]\((.+?)\)/", "[[$1>$2]]", $body);
		
		// 改行
		$body = preg_replace("/<br\s?\/?>/", "&br;", $body);
		
		$line = $head . $body . $foot;
		return $line;
	}
	
	public function getList($i) {
		return $this->listChars[$i];
	}
	
	public function setList($i, $c) {
		$this->listChars[$i] = $c;
	}
	
	public function getListLevel() {
		return $this->listLevel;
	}
	
	
	
}



