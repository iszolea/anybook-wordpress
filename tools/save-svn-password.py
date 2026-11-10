"""Saves the WordPress.org SVN password for the Anybook plugin into Windmill (admins workspace,
secret variable f/anybook/wordpress_org_svn_password). You type it; it is never shown or written to disk.

Run:  python C:\\Users\\jnich\\Projects\\Anybook\\save-svn-password.py
"""
import getpass
import sys

sys.path.insert(0, r"C:\Users\jnich\Projects\AiNativeHalo\tools")
import vpsmcp  # noqa: E402

PATH = "f/anybook/wordpress_org_svn_password"
DESC = "WordPress.org SVN password for the plugin 'anybook' (account anybookapp). Read by anybook-wordpress tools/release.sh."

pw = getpass.getpass("Paste the WordPress.org SVN password (nothing will show), then press Enter: ").strip()
if not pw:
    sys.exit("Nothing entered; nothing saved.")

vpsmcp._rpc("initialize", {"protocolVersion": "2025-03-26", "capabilities": {}, "clientInfo": {"name": "svn-pw", "version": "1"}})
existing = vpsmcp.call("listVariable", workspace_id="admins", path_start=PATH)
if existing:
    vpsmcp.call("updateVariable", workspace_id="admins", path=PATH, value=pw, is_secret=True, description=DESC)
    print("Updated", PATH)
else:
    vpsmcp.call("createVariable", workspace_id="admins", path=PATH, value=pw, is_secret=True, description=DESC)
    print("Saved", PATH)
