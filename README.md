# 🌉 PHP MCP Bridge

**SearXNG + MCP Protocol bridge in pure PHP. No dependencies. No frameworks. Just `php -S`.**

A lightweight Model Context Protocol server that bridges AI clients to SearXNG search and local filesystem operations. Built with zero dependencies — just PHP's built-in server and curl.

## ⚡ Quick Start

```bash
# Start SearXNG backend
podman-compose up -d

# Start MCP bridge
php -S 127.0.0.1:8001 mcp_bridge.php
```

Then point your MCP-compatible client to `http://127.0.0.1:8001/mcp_bridge.php`

## 🛠️ Supported Tools

| Tool | Description |
|------|-------------|
| `search` | Search the web via SearXNG (returns top 5 results) |
| `read_file` | Read file contents from disk |
| `write_file` | Create or overwrite files (auto-creates directories) |
| `run_command` | Execute shell/Godot/Blender commands |
| `list_dir` | List directory contents |

## 📡 Resources

| Resource | Description |
|----------|-------------|
| `env://system_info` | Current date/time and system context rules |

## 🏗️ Architecture

```
Client (PicoClaw / llama.cpp / other)
         │
         ▼ GET → SSE endpoint discovery
  mcp_bridge.php :8001
         │
         ▼ POST → tools/call → search
  SearXNG :8082
```

## 📁 Files

| File | Purpose |
|------|---------|
| `mcp_bridge.php` | MCP protocol server (SSE handshake, tools, resources) |
| `docker-compose.yml` | SearXNG + Valkey stack |
| `.env.example` | Copy to `.env` for SearXNG configuration |

## 🤝 Compatible Clients

- [PicoClaw](https://github.com/picoclav/picoclav)
- [llama.cpp server](https://github.com/ggerganov/llama.cpp)
- Any MCP-compatible application

## 📋 Requirements

- PHP 8+ (with built-in server)
- Podman or Docker + podman-compose
- MCP-compatible client

## 🔧 Configuration

Copy `.env.example` to `.env` and adjust SearXNG settings as needed:

```bash
cp .env.example .env
```

## 📄 License

MIT
