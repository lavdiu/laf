<?php
$rt = new \Intrepicure\RoutingTablePublic();
$module = \Laf\Util\UrlParser::getModule();
$rt->findByKeyword($module);


if ($rt->recordExists()) {

    if (!$rt->getNotemplateVal()) {
        include('../../app/view/html/html_header.page');
        include('../../app/view/html/header.page');
    }

    if ($rt->getRequiresLoginVal()) {

    } else {
        $pageFile = __DIR__ . '/../../app/view/' . $rt->getPageFileVal();
        if (file_exists($pageFile)) {
            include_once($pageFile);
        }
    }

    if (!$rt->getNotemplateVal()) {
        include('../../app/view/html/footer.page');
        include('../../app/view/html/html_footer.page');
    }
} else {
    header('location:'.$settings->getProperty('404'));
    exit;
}



