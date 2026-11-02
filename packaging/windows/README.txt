DeathPlanSwitch for Windows 10/11 and Windows Server
====================================================

What you get
  Two background tasks that start at boot and run as
  NT AUTHORITY\LOCAL SERVICE (a low-privilege account, not SYSTEM):
    "DeathPlanSwitch Web"        the web interface
    "DeathPlanSwitch Scheduler"  the check-in and notification scheduler
  They restart every minute if they stop.

Requirements
  - PHP 8.1 or newer with the curl, mbstring, openssl and sodium extensions
    enabled in php.ini. Install with:  winget install PHP.PHP.8.4
  - PHP must sit in a folder that only administrators can write to (see
    "PHP folder permissions" below). The installer refuses otherwise.
  - An elevated (Administrator) PowerShell.

Install
  1. Unpack the zip.
  2. Open PowerShell as Administrator in the unpacked folder.
  3. Run:  powershell -ExecutionPolicy Bypass -File .\install.ps1
     Options:  -PhpPath "C:\Program Files\PHP\php.exe"   -Listen 127.0.0.1:8080
  4. Open the printed address and finish the setup in the browser.

  Running install.ps1 again updates the program files and keeps your data.

Where things are
  Program files  %ProgramFiles%\DeathPlanSwitch  (read-only for LOCAL SERVICE)
  Data           %ProgramData%\DeathPlanSwitch   (Administrators and SYSTEM full,
                 LOCAL SERVICE modify, nobody else)

PHP folder permissions
  Whoever can replace php.exe, a DLL next to it, or its folder (or a parent
  folder) controls the service. install.ps1 checks the php.exe folder, php.exe
  and every parent folder (and every file and subfolder inside the PHP folder,
  such as ext\ and php.ini) BEFORE it runs php, and refuses if any account other
  than SYSTEM, Administrators, TrustedInstaller or CREATOR OWNER can write.
  A folder such as C:\php inherits write access for Authenticated Users from C:\,
  and a winget install for the current user lands in your profile; both fail.
  Easiest fix: put PHP in "%ProgramFiles%\PHP" (administrators only by default).
  Or fix the folder from an elevated prompt (replace C:\php with your folder):
    icacls "C:\php" /setowner *S-1-5-32-544 /T /C /L
    icacls "C:\php" /reset /T /C /L
    icacls "C:\php" /inheritance:r /grant:r *S-1-5-18:(OI)(CI)F *S-1-5-32-544:(OI)(CI)F *S-1-5-32-545:(OI)(CI)RX
  If the installer names a parent folder (for example C:\tools), run the setowner command (without /T /C /L) and the last command
  on it too. Users keep read and execute, which LOCAL SERVICE needs.

Security notes
  Both tasks run as LOCAL SERVICE. The program folder is read-only for it; it
  can write only to the data folder.
  An existing data folder is reused only if it is owned by SYSTEM or
  Administrators and holds no junction or symbolic link and no file owned by
  anyone else (LOCAL SERVICE is fine). Otherwise the installer refuses.
  The full path of php.exe is written into the task at install time.
  The web server listens on 127.0.0.1 by default. To reach it from other
  machines, put a reverse proxy with HTTPS in front of it.

Uninstall
  .\uninstall.ps1              removes the tasks and program files, keeps data
  .\uninstall.ps1 -RemoveData  also deletes the data folder
  Neither follows junctions; a junction in place of a folder stops the script.
