/**
 * Minimal leveled logger. Writes to stderr only — stdout belongs to the
 * stdio MCP transport and must never receive log noise.
 *
 * Level comes from BRIDGISTIC_LOG_LEVEL (error | warn | info | debug),
 * default "info". Never log secrets: callers must pass pre-redacted values.
 */

const LEVELS = ["error", "warn", "info", "debug"] as const;
export type LogLevel = (typeof LEVELS)[number];

function currentLevel(): number {
  const raw = (process.env.BRIDGISTIC_LOG_LEVEL || "info").toLowerCase();
  const idx = LEVELS.indexOf(raw as LogLevel);
  return idx === -1 ? LEVELS.indexOf("info") : idx;
}

function emit(level: LogLevel, message: string): void {
  if (LEVELS.indexOf(level) > currentLevel()) return;
  console.error(`[bridgistic] ${level}: ${message}`);
}

export const log = {
  error: (msg: string): void => emit("error", msg),
  warn: (msg: string): void => emit("warn", msg),
  info: (msg: string): void => emit("info", msg),
  debug: (msg: string): void => emit("debug", msg),
};
