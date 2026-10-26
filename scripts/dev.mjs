import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
const frontend = fileURLToPath(new URL('../react-erp-app/', import.meta.url));
const children = [];
let stopping = false;

function stop(code = 0) {
  if (stopping) return;
  stopping = true;
  process.exitCode = code;
  for (const child of children) {
    if (child.pid && child.exitCode === null) {
      try { process.kill(-child.pid, 'SIGTERM'); } catch (error) {
        if (error.code !== 'ESRCH') console.error(error.message);
      }
    }
  }
}

function start(command, args, cwd, env = process.env) {
  const child = spawn(command, args, { cwd, env, stdio: 'inherit', detached: true });
  children.push(child);
  child.on('error', (error) => { console.error(error.message); stop(1); });
  child.on('exit', (code) => { if (!stopping) stop(code ?? 1); });
}

process.on('SIGINT', () => stop());
process.on('SIGTERM', () => stop());
console.log('React: http://127.0.0.1:5173 | Laravel: http://127.0.0.1:8000');
start('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8000', '--tries=1'], root);
start('npm', ['run', 'dev', '--', '--host=127.0.0.1', '--port=5173', '--strictPort'], frontend);
