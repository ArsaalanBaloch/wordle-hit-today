import { execFileSync } from "node:child_process";
import { readFile } from "node:fs/promises";

const files = execFileSync("git", ["ls-files", "-co", "--exclude-standard"], {
  encoding: "utf8",
})
  .split(/\r?\n/)
  .filter(Boolean);

const checks = [
  {
    name: "private key",
    pattern: /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/,
  },
  { name: "GitHub token", pattern: /\b(?:ghp|github_pat)_[A-Za-z0-9_]{20,}\b/ },
  { name: "AWS access key", pattern: /\bAKIA[0-9A-Z]{16}\b/ },
];

const findings = [];
for (const file of files) {
  let contents;
  try {
    contents = await readFile(file, "utf8");
  } catch {
    continue;
  }
  for (const check of checks) {
    if (check.pattern.test(contents)) findings.push(`${file}: ${check.name}`);
  }
}

if (findings.length) {
  process.stderr.write(`Potential secrets found:\n${findings.join("\n")}\n`);
  process.exitCode = 1;
} else {
  process.stdout.write(
    `Secret scan passed for ${files.length} text-readable files.\n`,
  );
}
