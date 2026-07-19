<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$settings = file_get_contents($root.'admin/set.php');
$homepage = file_get_contents($root.'index.php');
$install = file_get_contents($root.'install/install.sql');
$failures = array();

function check_admin_keywords($condition, $message){
    global $failures;
    if(!$condition) $failures[] = $message;
}

check_admin_keywords(strpos($settings, '<label for="qfSiteKeywordsInput">网站关键词</label>') !== false, 'website keywords label is missing');
check_admin_keywords(strpos($settings, 'id="qfSiteKeywordsInput" name="keywords"') !== false, 'website keywords input is missing');
check_admin_keywords(strpos($settings, 'maxlength="255"') !== false, 'website keywords input length limit is missing');
check_admin_keywords(strpos($settings, "saveSetting('keywords', \$keywords_save)") !== false, 'website keywords are not saved');
check_admin_keywords(strpos($settings, "mb_substr(trim(isset(\$_POST['keywords'])") !== false, 'website keywords are not normalized before saving');
check_admin_keywords(strpos($homepage, '<meta name="keywords"') !== false && strpos($homepage, "isset(\$conf['keywords'])") !== false, 'homepage keywords metadata is missing or unsafe for older databases');
check_admin_keywords(strpos($install, "('keywords',") !== false, 'fresh installations do not include a default keywords setting');

if($failures){
    fwrite(STDERR, "Admin keywords settings tests failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Admin keywords settings tests passed.\n";
?>
