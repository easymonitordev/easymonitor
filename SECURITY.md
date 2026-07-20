# Security Policy

## Reporting a Vulnerability

Please do not report security vulnerabilities through public GitHub issues, discussions, or pull requests.

Instead, use GitHub's private vulnerability reporting: go to the [Security tab](https://github.com/easymonitordev/easymonitor/security) and click "Report a vulnerability", or open a draft advisory directly at https://github.com/easymonitordev/easymonitor/security/advisories/new.

Your report stays private while a fix is developed. Please include what you can of the following:

- The affected version or commit
- Steps to reproduce or a proof of concept
- The impact you believe it has (who can exploit it, and what they gain)

## What to Expect

EasyMonitor is maintained by a single developer, so response times are best-effort:

- Acknowledgment of your report within 72 hours
- A status update or fix timeline within 14 days
- Credit in the release notes and security advisory once a fix ships, unless you prefer to remain anonymous

Please give us reasonable time to release a fix before disclosing publicly. We will coordinate the disclosure timing with you.

## Supported Versions

Only the latest release receives security fixes. If you are self-hosting, upgrade with:

```
git pull
./setup.sh --upgrade
```

See UPGRADING.md for details.
