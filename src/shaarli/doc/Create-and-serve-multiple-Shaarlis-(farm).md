#Create and serve multiple Shaarlis (farm)
Example bash script (creates multiple shaarli instances and generates an HTML index of them)

```bash
#!/bin/bash
set -o errexit
set -o nounset

#config
shaarli_base_dir='/var/www/shaarli'
accounts='bob john whatever username'
shaarli_repo_url='https://github.com/shaarli/Shaarli'
ref="master"

#clone multiple shaarli instances
if [ ! -d "$shaarli_base_dir" ]; then mkdir "$shaarli_base_dir"; fi[](.html)
   
for account in $accounts; do
    if [ -d "$shaarli_base_dir/$account" ];[](.html)
	then echo "[info] account $account already exists, skipping";[](.html)
	else echo "[info] creating new account $account ..."; git clone --quiet "$shaarli_repo_url" -b "$ref" "$shaarli_base_dir/$account"; fi[](.html)
done

#generate html index of shaarlis
htmlhead='<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<!-- Minimal html template thanks to http://www.sitepoint.com/a-minimal-html-document/ -->
<html lang="en">
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>My Shaarli farm</title>
		<style>body {font-family: "Open Sans"}</style>
	</head>
	<body>
	<div>
	<h1>My Shaarli farm</h1>
	<ul style="list-style-type: none;">'

accountlinks=''
    
htmlfooter='
	</ul>
	</div>
	</body>
</html>'    
    


for account in $accounts; do accountlinks="$accountlinks\n<li><a href=\"$account\">$account</a></li>"; done
if [ -d "$shaarli_base_dir/index.html" ]; then echo "[removing old index.html]"; rm "$shaarli_base_dir/index.html" ]; fi[](.html)
echo "[info] generating new index of shaarlis"[](.html)
echo -e "$htmlhead $accountlinks $htmlfooter" > "$shaarli_base_dir/index.html"
echo '[info] done.'[](.html)
echo "[info] list of accounts: $accounts"[](.html)
echo "[info] contents of $shaarli_base_dir:"[](.html)
tree -a -L 1 "$shaarli_base_dir"
```

This script just serves as an example. More precise or complex (applying custom configuration, etc) automation is possible using configuration management software like [Ansible](https://www.ansible.com/)[](.html)
