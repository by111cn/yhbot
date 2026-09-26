<?php
// 占位文件，防止目录被直接列出
http_response_code(403);
header('Content-Type: text/plain; charset=utf-8');
echo 'Forbidden';
