# CMS Vermont Customizations (oe-module-cmsvt)

Site customizations for CMS Vermont practices, kept out of core OpenEMR.
Enable it in Administration → Modules → Manage Modules (Register, Install,
Enable).

## Console commands

### `cmsvt:x12-sftp-password`

Rotates the SFTP password stored for an X12 partner, such as the NGS
clearinghouse login. It replaces `contrib/multisite/updateNgsPassword.php`
from the 7.0.1 branch.

```sh
# prompts for the new password without echoing it
php bin/console cmsvt:x12-sftp-password --site=default --login=<sftp login>

# non-interactive: read the password from STDIN
php bin/console cmsvt:x12-sftp-password --site=default --login=<sftp login> --password-stdin < password.txt
```

Unlike the old script, the password is never passed on the command line, so
it doesn't end up in the process list or shell history. The command fails if
`auto_sftp_claims_to_x12_partner` is off, or if the login doesn't match
exactly one partner. The value is stored with `encryptForDatabase()`, the
counterpart of the `decryptFromDatabase()` that X12RemoteTracker and EDI270
use to read it.
