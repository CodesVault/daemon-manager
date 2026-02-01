<?php

// This script triggers a PHP warning/notice
echo $undefinedVariable;
print_r((object)['name' => 'Test']);
// echo "Script executed with an error\n";
throw new Exception("This is a test exception");
