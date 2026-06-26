<?php
// 1. COMPLETELY OPEN CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
// Add 'mcp-protocol-version' specifically - the browser is looking for this!
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, mcp-protocol-version");

// 2. IMMEDIATELY EXIT ON OPTIONS (Crucial for Web UIs)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 3. PICOCLAW SSE HANDSHAKE (Only triggers if a GET request comes in)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    // Tells PicoClaw to send its subsequent POSTs right back to this file
    $postUrl = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

    echo "event: endpoint\n";
    echo "data: " . json_encode($postUrl) . "\n\n";
    flush();
    exit;
}

// 4. CAPTURE INPUT (For your Web UI and PicoClaw Tool calls via POST)
$rawData = file_get_contents('php://input');
$input = json_decode($rawData, true);

// Handle empty/probe requests
if (!$input) {
    http_response_code(200);
    exit;
}

$method = $input['method'] ?? '';
$id = $input['id'] ?? null;
$searxng_url = "http://127.0.0.1:8082/search?format=json&q=";

// 5. PROTOCOL RESPONSES
header('Content-Type: application/json');

switch ($method) {
    case 'initialize':
        echo json_encode([
            "jsonrpc" => "2.0",
            "id" => $id,
            "result" => [
                "protocolVersion" => "2024-11-05",
                "capabilities" => ["tools" => (object)[], "resources" => (object)[]],
                "serverInfo" => ["name" => "php-bridge", "version" => "1.0"]
            ]
        ]);
        break;

    case 'notifications/initialized':
        // The browser MUST get a 200 OK here or it marks it as "Failed to fetch"
        http_response_code(200);
        break;

	case 'resources/list':
		echo json_encode([
			"jsonrpc" => "2.0", "id" => $id,
			"result" => ["resources" => [
				["uri" => "env://system_info", "name" => "System Context", "mimeType" => "text/plain"]
			]]
		]);
		break;

	case 'resources/read':
		$current_date = date('Y-m-d H:i:s');
		$context = "Current Date/Time: $current_date. Rules: Do not lie, be concise, stay focused.";
		echo json_encode([
			"jsonrpc" => "2.0", "id" => $id,
			"result" => ["contents" => [
				["uri" => "env://system_info", "mimeType" => "text/plain", "text" => $context]
			]]
		]);
		break;

    case 'tools/list':
        echo json_encode([
            "jsonrpc" => "2.0",
            "id" => $id,
            "result" => [
                "tools" => [
                    ["name" => "search", "description" => "Search via SearXNG", "inputSchema" => ["type" => "object", "properties" => ["query" => ["type" => "string"]], "required" => ["query"]]],
                    ["name" => "read_file", "description" => "Read a file from disk", "inputSchema" => ["type" => "object", "properties" => ["path" => ["type" => "string"]], "required" => ["path"]]],
                    ["name" => "write_file", "description" => "Write/Create a file (GDScript, Python, HTML, etc.)", "inputSchema" => ["type" => "object", "properties" => ["path" => ["type" => "string"], "content" => ["type" => "string"]], "required" => ["path", "content"]]],
                    ["name" => "run_command", "description" => "Run Godot/Blender/Shell commands", "inputSchema" => ["type" => "object", "properties" => ["command" => ["type" => "string"], "cwd" => ["type" => "string"]], "required" => ["command"]]],
                    ["name" => "list_dir", "description" => "List project directory contents", "inputSchema" => ["type" => "object", "properties" => ["path" => ["type" => "string"]], "required" => ["path"]]]
                ]
            ]
        ]);
        break;

    case 'tools/call':
        $tool = $input['params']['name'] ?? '';
        $args = $input['params']['arguments'] ?? [];
        $out = "";

        switch ($tool) {
            case 'search':
                $ch = curl_init($searxng_url . urlencode($args['query'] ?? ''));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $res = json_decode(curl_exec($ch), true);
                curl_close($ch);
                if (isset($res['results'])) {
                    foreach (array_slice($res['results'], 0, 5) as $r) {
                        $out .= "Title: {$r['title']}\nSnippet: {$r['content']}\n\n";
                    }
                }
                break;

            case 'read_file':
                $out = file_exists($args['path']) ? file_get_contents($args['path']) : "Error: File not found.";
                break;

            case 'write_file':
                $dir = dirname($args['path']);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $out = (file_put_contents($args['path'], $args['content']) !== false) ? "Success: Written to {$args['path']}" : "Error: Write failed.";
                break;

            case 'run_command':
                $cwd = $args['cwd'] ?? __DIR__;
                $process = proc_open($args['command'], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes, $cwd);
                if (is_resource($process)) {
                    $stdout = stream_get_contents($pipes[1]);
                    $stderr = stream_get_contents($pipes[2]);
                    fclose($pipes[1]); fclose($pipes[2]);
                    proc_close($process);
                    $out = "STDOUT:\n$stdout\n\nSTDERR:\n$stderr";
                } else { $out = "Error: Execution failed."; }
                break;

            case 'list_dir':
                $out = is_dir($args['path']) ? implode("\n", scandir($args['path'])) : "Error: Directory not found.";
                break;
        }

        echo json_encode(["jsonrpc" => "2.0", "id" => $id, "result" => ["content" => [["type" => "text", "text" => $out ?: "Done."]], "isError" => false]]);
        break;

    default:
        // Accept everything else silently to keep the connection alive
        echo json_encode(["jsonrpc" => "2.0", "id" => $id, "result" => null]);
}
