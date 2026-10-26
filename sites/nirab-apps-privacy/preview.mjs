import http from 'node:http';
import path from 'node:path';
import fs from 'node:fs/promises';
const root = path.resolve('dist');
const types = {'.html':'text/html; charset=utf-8','.css':'text/css; charset=utf-8','.png':'image/png','.otf':'font/otf'};
http.createServer(async (req,res) => {
  try {
    const url = new URL(req.url,'http://127.0.0.1');
    const relative = decodeURIComponent(url.pathname) === '/' ? 'index.html' : decodeURIComponent(url.pathname).replace(/^\/+/, '');
    const file = path.resolve(root, relative);
    if (!file.startsWith(root + path.sep)) { res.writeHead(403); res.end(); return; }
    const bytes = await fs.readFile(file);
    res.writeHead(200, {'Content-Type':types[path.extname(file)] || 'application/octet-stream','Cache-Control':'no-store'});
    res.end(bytes);
  } catch { res.writeHead(404); res.end('Not found'); }
}).listen(4177,'127.0.0.1',()=>console.log('Local: http://127.0.0.1:4177'));
