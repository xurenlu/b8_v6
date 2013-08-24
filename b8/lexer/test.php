<?php
include "./lexer_u.php";
$lexer = new b8_lexer_u(array());
print_r( $lexer->get_tokens("我们都是好朋友"));

include "./lexer_default.php";
$lexer = new b8_lexer_default(array());
print_r( $lexer->get_tokens("我们都是好朋友"));
