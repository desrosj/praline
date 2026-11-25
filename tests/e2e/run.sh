#!/usr/bin/env bash
#
# End-to-end check: a real WordPress, served by PHP's built-in server, with
# this plugin active, asked for pages the way BonBon asks for them. With
# E2E_WITH_MARKDOWN_ALTERNATE=1, the standalone Markdown Alternate (in
# ../markdown-alternate) is active too, and serves the Markdown in place of
# the bundled copy. Needs php, curl, and a MySQL server (root, no password,
# on 127.0.0.1 unless E2E_DB_* say otherwise). Drops and recreates the
# database named by E2E_DB_NAME (praline_e2e).

set -u
cd "$(dirname "$0")"

PORT="${E2E_PORT:-8765}"
BASE="http://localhost:$PORT"
DB="${E2E_DB_NAME:-praline_e2e}"
DOCROOT="$(cd ../../../../.. && pwd)"
BONBON_ACCEPT='Accept: text/markdown, text/x-opml'
BROWSER_ACCEPT='Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
export E2E_LOG="${TMPDIR:-/tmp}/praline-e2e-debug.log"
WITH_MA="${E2E_WITH_MARKDOWN_ALTERNATE:-}"
export E2E_WITH_MARKDOWN_ALTERNATE="$WITH_MA"
if [ -n "$WITH_MA" ] && [ ! -f ../../../markdown-alternate/markdown-alternate.php ]; then
	echo "E2E_WITH_MARKDOWN_ALTERNATE needs Markdown Alternate in wp-content/plugins/markdown-alternate"; exit 1
fi

mysql -u"${E2E_DB_USER:-root}" -h"${E2E_DB_HOST:-127.0.0.1}" ${E2E_DB_PASSWORD:+-p"$E2E_DB_PASSWORD"} \
	-e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\`;" || exit 1
rm -f "$E2E_LOG"

IDS="$(php install.php)" || { echo "install failed"; exit 1; }

php -S "localhost:$PORT" -t "$DOCROOT" router.php >/dev/null 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null' EXIT
for _ in $(seq 50); do curl -s -o /dev/null "$BASE/" && break; sleep 0.1; done

passed=0
failed=0
check() { # check "description" condition-exit-status
	if [ "$2" -eq 0 ]; then
		passed=$((passed + 1)); echo "  ok   $1"
	else
		failed=$((failed + 1)); echo "  FAIL $1"
	fi
}
headers() { curl -s -o /dev/null -D - "$@" | tr -d '\r'; }
header_value() { grep -i "^$1:" | head -1 | cut -d' ' -f2-; }

echo "A browser and BonBon at the same address"
h="$(headers -H "$BROWSER_ACCEPT" "$BASE/")"
echo "$h" | grep -q '^HTTP/1.1 200'; check "a browser gets 200" $?
echo "$h" | header_value Content-Type | grep -q '^text/html'; check "a browser gets HTML" $?

h="$(headers -H "$BONBON_ACCEPT" "$BASE/")"
echo "$h" | grep -q '^HTTP/1.1 303'; check "BonBon is sent on with a 303" $?
[ "$(echo "$h" | header_value Location)" = "$BASE/index.md" ]; check "to /index.md" $?

final="$(curl -s -L -o /dev/null -w '%{url_effective} %{http_code} %{content_type}' -H "$BONBON_ACCEPT" "$BASE/")"
[ "$final" = "$BASE/index.md 200 text/markdown; charset=UTF-8" ]; check "following it, BonBon gets Markdown at /index.md ($final)" $?

echo "Front matter"
home="$(curl -s -L -H "$BONBON_ACCEPT" "$BASE/")"
echo "$home" | grep -Eq '^pubDate: "[A-Z][a-z]{2}, [0-9]{2} [A-Z][a-z]{2} [0-9]{4} [0-9:]{8} GMT"$'; check "the home page has an RFC 822 pubDate" $?
echo "$home" | grep -qx "link: \"$BASE/\""; check "its link is the HTML home page" $?

older="$(curl -s -L -H "$BONBON_ACCEPT" "$BASE/2026/11/01/older-rhubarb-post/")"
newer="$(curl -s -L -H "$BONBON_ACCEPT" "$BASE/2026/11/02/newer-rhubarb-post/")"
echo "$older" | grep -qx "urlNext: \"$BASE/2026/11/02/newer-rhubarb-post.md\""; check "the older post's right arrow goes to the newer" $?
echo "$newer" | grep -qx "urlPrev: \"$BASE/2026/11/01/older-rhubarb-post.md\""; check "the newer post's left arrow goes to the older" $?
echo "$newer" | grep -qx "urlNext: \"$BASE/index.md\""; check "the newest post's right arrow skips the protected post and goes on to the front page" $?
echo "$home" | grep -qx "urlPrev: \"$BASE/2026/11/02/newer-rhubarb-post.md\""; check "the front page's left arrow goes back to the newest post" $?
echo "$home" | grep -qx "urlNext: \"$BASE/about.md\""; check "the front page's right arrow goes to the next page" $?
echo "$newer" | grep -qx 'description: "The newer one."'; check "the newer post's description is its excerpt" $?

echo "/.well-known/toc.opml"
h="$(headers "$BASE/.well-known/toc.opml")"
echo "$h" | grep -q '^HTTP/1.1 200'; check "200" $?
[ "$(echo "$h" | header_value Content-Type)" = "text/x-opml; charset=UTF-8" ]; check "text/x-opml" $?
toc="$(curl -s "$BASE/.well-known/toc.opml")"
echo "$toc" | php -r 'exit(simplexml_load_string(stream_get_contents(STDIN)) === false ? 1 : 0);'; check "parses as XML" $?
first="$(echo "$toc" | grep -o 'url="[^"]*"' | head -1)"
[ "$first" = "url=\"$BASE/2026/11/02/newer-rhubarb-post.md\"" ]; check "the newest post is the first line" $?
first_page="$(echo "$toc" | grep -o 'url="[^"]*\.md"' | grep -v '/2026/' | head -1)"
[ "$first_page" = "url=\"$BASE/index.md\"" ]; check "the front page is the first page, after the posts" $?
echo "$toc" | grep -q 'type="calendarYear" name="2026"'; check "posts are under a 2026 calendarYear" $?
echo "$toc" | grep -q 'protected-rhubarb-post'; [ $? -ne 0 ]; check "the protected post isn't listed" $?
echo "$toc" | grep -q "$BASE/blog.md"; [ $? -ne 0 ]; check "the posts page isn't listed" $?
all_ok=0
for url in $(echo "$toc" | grep -o 'url="[^"]*"' | sed 's/url="//; s/"$//'); do
	got="$(curl -s -o /dev/null -w '%{http_code} %{content_type}' -H "$BONBON_ACCEPT" "$url")"
	if [ "$got" != "200 text/markdown; charset=UTF-8" ]; then echo "       $url -> $got"; all_ok=1; fi
done
check "every address in the outline answers BonBon with Markdown" $all_ok

echo "/.well-known/bonbon.json and search"
json="$(curl -s "$BASE/.well-known/bonbon.json")"
search_url="$(echo "$json" | php -r 'echo json_decode(stream_get_contents(STDIN), true)["urlSearch"] ?? "";')"
[ "$search_url" = "$BASE/.well-known/bonbon-search" ]; check "bonbon.json names the search" $?
h="$(headers "$search_url?q=rhubarb")"
[ "$(echo "$h" | header_value Content-Type)" = "text/markdown; charset=UTF-8" ]; check "search answers Markdown" $?
results="$(curl -s "$search_url?q=rhubarb")"
echo "$results" | grep -q "($BASE/2026/11/02/newer-rhubarb-post.md)"; check "search finds a post, linked as Markdown" $?
echo "$results" | grep -q 'protected-rhubarb-post'; [ $? -ne 0 ]; check "search leaves out the protected post" $?

if [ -z "$WITH_MA" ]; then
	echo "Archives, which Markdown Alternate has no Markdown for"
	for path in /author/admin/ /2026/11/ /2026/11/02/; do
		got="$(curl -s -o /dev/null -w '%{http_code} %{content_type}' -H "$BONBON_ACCEPT" "$BASE$path")"
		[ "$got" = "200 text/html; charset=UTF-8" ]; check "BonBon at $path gets the HTML, not a 303 to a 404 ($got)" $?
	done
else
	echo "Archives, routed by the standalone Markdown Alternate's own rules"
	got="$(curl -s -o /dev/null -w '%{http_code}' -H "$BONBON_ACCEPT" "$BASE/2026/11/")"
	[ "$got" = "303" ]; check "BonBon at /2026/11/ gets the standalone plugin's 303, so it is the one routing ($got)" $?
fi

echo "Content-Location"
h="$(headers "$BASE/2026/11/02/newer-rhubarb-post/?format=markdown")"
[ "$(echo "$h" | header_value Content-Type)" = "text/markdown; charset=UTF-8" ]; check "?format=markdown answers Markdown" $?
[ "$(echo "$h" | header_value Content-Location)" = "$BASE/2026/11/02/newer-rhubarb-post.md" ]; check "with Content-Location naming the .md file" $?
h="$(headers -H "$BROWSER_ACCEPT" "$BASE/2026/11/02/newer-rhubarb-post/")"
echo "$h" | grep -qi '^Content-Location:'; [ $? -ne 0 ]; check "no Content-Location on the HTML page" $?

echo "Redirects on template_redirect run before the Markdown"
newer_id="$(echo "$IDS" | php -r 'echo json_decode(stream_get_contents(STDIN), true)["newer"];')"
got="$(curl -s -o /dev/null -w '%{http_code} %{content_type}' "$BASE/?format=markdown")"
[ "$got" = "200 text/markdown; charset=UTF-8" ]; check "the static front page's ?format=markdown is still Markdown ($got)" $?
if [ -z "$WITH_MA" ]; then
	h="$(headers "$BASE/?p=$newer_id&format=markdown")"
	echo "$h" | grep -q '^HTTP/1.1 301'; check "WordPress's canonical redirect sends ?p= on to the post's address" $?
	[ "$(echo "$h" | header_value Location)" = "$BASE/2026/11/02/newer-rhubarb-post/?format=markdown" ]; check "keeping ?format=markdown" $?
	LOGIN='X-E2E-Login-Required: 1'
	for path in /2026/11/02/newer-rhubarb-post.md /2026/11/02/newer-rhubarb-post/?format=markdown /index.md; do
		got="$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -H "$LOGIN" -H "$BONBON_ACCEPT" "$BASE$path")"
		[ "$got" = "302 $BASE/wp-login.php" ]; check "with a login required at priority 10, $path is sent to log in ($got)" $?
	done
	got="$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -H "$LOGIN" -H "$BONBON_ACCEPT" "$BASE/2026/11/02/newer-rhubarb-post/")"
	[ "$got" = "302 $BASE/wp-login.php" ]; check "and so is BonBon's Accept: text/markdown, not sent on to the .md ($got)" $?
fi

if [ -z "$WITH_MA" ]; then
	echo "Standalone: only this plugin is active"
	label="the bundled Markdown Alternate"
else
	echo "With the standalone Markdown Alternate active"
	label="the standalone Markdown Alternate"
fi
got="$(curl -s -o /dev/null -w '%{http_code} %{content_type}' -H "$BONBON_ACCEPT" "$BASE/2026/11/02/newer-rhubarb-post.md")"
[ "$got" = "200 text/markdown; charset=UTF-8" ]; check "$label serves a .md address ($got)" $?
curl -s -H "$BONBON_ACCEPT" "$BASE/2026/11/02/newer-rhubarb-post.md" | grep -q '^categories:'; [ $? -ne 0 ]; check "its front matter has no category links" $?
links="$(curl -s -H "$BROWSER_ACCEPT" "$BASE/2026/11/02/newer-rhubarb-post/" | grep -c 'rel="alternate" type="text/markdown"')"
[ "$links" = "1" ]; check "the HTML page has one Markdown alternate link, not one from each copy ($links)" $?

echo "Dev scripts don't run over the web"
PLUGIN_PORT=$((PORT + 1))
php -S "localhost:$PLUGIN_PORT" -t "$(cd ../.. && pwd)" >/dev/null 2>&1 &
PLUGIN_SERVER=$!
trap 'kill $SERVER $PLUGIN_SERVER 2>/dev/null' EXIT
for _ in $(seq 50); do curl -s -o /dev/null "http://localhost:$PLUGIN_PORT/" && break; sleep 0.1; done
for script in tests/e2e/install.php bin/mark-modified.php; do
	body="$(curl -s "http://localhost:$PLUGIN_PORT/$script")"
	[ -z "$body" ]; check "$script over the web does nothing (${#body} bytes)" $?
done

echo "PHP notices and warnings"
if [ -s "$E2E_LOG" ] && grep -E 'PHP (Warning|Notice|Fatal|Deprecated)' "$E2E_LOG" | grep -q 'praline'; then
	grep -E 'praline' "$E2E_LOG" | head -5; check "none from this plugin" 1
else
	check "none from this plugin" 0
fi

echo
echo "$passed passed, $failed failed"
[ "$failed" -eq 0 ]
