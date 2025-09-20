<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::local('support-chat', \App\Mcp\Servers\SupportChatServer::class);
Mcp::web('/mcp/support-chat', \App\Mcp\Servers\SupportChatServer::class);
