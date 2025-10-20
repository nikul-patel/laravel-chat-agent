### Issue to Integrate this functionality with other project
- [x] Model & Migration is missing — package now ships `Soha\Chat\Models\ChatMessage` plus migrations/factories under `packages/soha/chat/database`.
- [x] Service is missing — `Soha\Chat\Services\ChatAgentService` and supporting container bindings live inside the package.
- [x] MCP Resource, Server, Tools are missing — resources, tools, and server classes reside under `packages/soha/chat/src/Mcp` and register automatically.
- [x] Soha CSS and JS Not loaded Properly — widget assets move to the package (`resources/{css,js}`) and inline by default, with optional publishing via `soha-chat-assets`.
