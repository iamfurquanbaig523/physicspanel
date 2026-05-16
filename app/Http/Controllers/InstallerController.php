<?php

namespace App\Http\Controllers;

use dacoto\LaravelWizardInstaller\Controllers\InstallFolderController;
use dacoto\LaravelWizardInstaller\Controllers\InstallServerController;
use Illuminate\Routing\Controller;

class InstallerController extends Controller {
    public function purchaseCodeIndex() {
        if (!(new InstallServerController())->check() || !(new InstallFolderController())->check()) {
            return redirect()->route('LaravelWizardInstaller::install.folders');
        }
        return redirect()->route('install.php-function.index');
    }


    public function checkPurchaseCode() {
        return redirect()->route('install.php-function.index');
    }

    public function phpFunctionIndex() {
        if (!(new InstallServerController())->check() || !(new InstallFolderController())->check()) {
            return redirect()->route('LaravelWizardInstaller::install.purchase_code');
        }
        return view('vendor.installer.steps.symlink_basedir_check', [
            'result' => $this->checkSymlink(),
            'baseDir' =>$this->checkBaseDir()
        ]);
    }

    public function checkSymlink(): bool
    {
        return function_exists('symlink');
    }
    public function checkBaseDir(): bool
    {
        $openBaseDir = ini_get('open_basedir');
        if ($openBaseDir) {
            return false;
        }
        return true;
    }

}
