<?php

use Laravel\Mcp\Facades\Mcp;
use Soha\Chat\Mcp\Servers\SupportChatServer;

Mcp::local('support-chat', SupportChatServer::class);
Mcp::web('/mcp/support-chat', SupportChatServer::class);
