import { readFileSync, existsSync } from "node:fs";
import type { Connection, SiteRegistry } from "../types.js";
import { log } from "./logger.js";

/**
 * Resolves which WordPress site a tool call targets.
 *
 * Two modes, both supported simultaneously:
 *   1. Single-site: env BRIDGISTIC_SITE_URL (or legacy WP_SITE_URL) +
 *      BRIDGISTIC_KEY_ID + BRIDGISTIC_KEY_SECRET. Available under alias "default".
 *   2. Multi-site: a JSON registry file (BRIDGISTIC_CONNECTIONS path)
 *      mapping aliases -> { siteUrl, keyId, secret }. The agent passes `site`.
 */
export class ConnectionRegistry implements SiteRegistry {
  private connections = new Map<string, Connection>();

  constructor() {
    this.loadFromEnv();
    this.loadFromFile();
  }

  /** Validate a site URL; returns the cleaned URL or null with a logged reason. */
  private static cleanSiteUrl(raw: string, source: string): string | null {
    const url = raw.trim().replace(/\/+$/, "");
    let parsed: URL;
    try {
      parsed = new URL(url);
    } catch {
      log.error(
        `${source}: "${raw}" is not a valid URL. Use the exact Site Address from WP Admin → Settings → General, e.g. https://example.com`
      );
      return null;
    }
    if (parsed.protocol !== "https:" && parsed.protocol !== "http:") {
      log.error(`${source}: "${raw}" must start with https:// (or http:// for local dev).`);
      return null;
    }
    if (parsed.hostname === "example.com") {
      log.warn(
        `${source}: site URL is still the placeholder "example.com" — edit your config and set your real site URL.`
      );
    }
    if (parsed.protocol === "http:" && !/^(localhost|127\.0\.0\.1|.*\.(local|test))$/.test(parsed.hostname)) {
      log.warn(`${source}: "${url}" uses plain http on a non-local host. Use https in production.`);
    }
    return url;
  }

  private loadFromEnv(): void {
    // BRIDGISTIC_SITE_URL is the documented name; WP_SITE_URL kept for
    // backwards compatibility with existing configs.
    const siteUrl = process.env.BRIDGISTIC_SITE_URL || process.env.WP_SITE_URL;
    const keyId = process.env.BRIDGISTIC_KEY_ID;
    const secret = process.env.BRIDGISTIC_KEY_SECRET;

    const set = [siteUrl, keyId, secret].filter(Boolean).length;
    if (set === 0) return; // Env mode simply not in use.
    if (set < 3) {
      log.warn(
        "Incomplete env connection: need all three of BRIDGISTIC_SITE_URL, BRIDGISTIC_KEY_ID, BRIDGISTIC_KEY_SECRET. " +
          `Currently set: ${[
            siteUrl && "BRIDGISTIC_SITE_URL",
            keyId && "BRIDGISTIC_KEY_ID",
            secret && "BRIDGISTIC_KEY_SECRET",
          ]
            .filter(Boolean)
            .join(", ") || "(none)"}.`
      );
      return;
    }

    const clean = ConnectionRegistry.cleanSiteUrl(siteUrl as string, "BRIDGISTIC_SITE_URL");
    if (!clean) return;

    if (!/^wpk_[0-9a-f]+$/i.test(keyId as string)) {
      log.warn(
        'BRIDGISTIC_KEY_ID does not look like a Bridgistic key id (expected "wpk_..."). Copy it from WP Admin → Bridgistic → Claude Setup.'
      );
    }

    this.connections.set("default", {
      alias: "default",
      siteUrl: clean,
      keyId: keyId as string,
      secret: secret as string,
    });
  }

  private loadFromFile(): void {
    const path = process.env.BRIDGISTIC_CONNECTIONS;
    if (!path) return;
    if (!existsSync(path)) {
      log.error(
        `BRIDGISTIC_CONNECTIONS points to "${path}" but no file exists there. ` +
          "Create it from connections.example.json (alias -> { siteUrl, keyId, secret })."
      );
      return;
    }

    try {
      const raw = JSON.parse(readFileSync(path, "utf8")) as Record<
        string,
        { siteUrl: string; keyId: string; secret: string }
      >;
      for (const [alias, conf] of Object.entries(raw)) {
        if (!conf?.siteUrl || !conf?.keyId || !conf?.secret) {
          log.warn(
            `Connection "${alias}" in ${path} is missing one of siteUrl / keyId / secret — skipped.`
          );
          continue;
        }
        const clean = ConnectionRegistry.cleanSiteUrl(conf.siteUrl, `connection "${alias}"`);
        if (!clean) continue;
        this.connections.set(alias, {
          alias,
          siteUrl: clean,
          keyId: conf.keyId,
          secret: conf.secret,
        });
      }
    } catch (err) {
      // Logged to stderr only; never break the server over a bad registry.
      log.error(`Failed to parse connections file ${path}: ${String(err)}`);
    }
  }

  /** List aliases the agent can target (no secrets). */
  list(): Array<{ alias: string; siteUrl: string }> {
    return [...this.connections.values()].map((c) => ({
      alias: c.alias,
      siteUrl: c.siteUrl,
    }));
  }

  /** Number of configured connections. */
  size(): number {
    return this.connections.size;
  }

  /**
   * Resolve a connection. If `alias` omitted, uses "default" when it's the
   * only connection, otherwise throws so the agent must disambiguate.
   */
  resolve(alias?: string): Connection {
    if (alias) {
      const conn = this.connections.get(alias);
      if (!conn) {
        throw new Error(
          `Unknown site alias "${alias}". Available: ${this.list()
            .map((c) => c.alias)
            .join(", ") || "(none configured)"}`
        );
      }
      return conn;
    }

    if (this.connections.size === 1) {
      return [...this.connections.values()][0];
    }
    if (this.connections.size === 0) {
      throw new Error(
        "No WordPress connections configured. Set BRIDGISTIC_SITE_URL / BRIDGISTIC_KEY_ID / BRIDGISTIC_KEY_SECRET, " +
          "or provide a BRIDGISTIC_CONNECTIONS registry file. Generate keys in WP Admin → Bridgistic → Claude Setup."
      );
    }
    throw new Error(
      `Multiple sites configured; specify "site". Available: ${this.list()
        .map((c) => c.alias)
        .join(", ")}`
    );
  }
}
