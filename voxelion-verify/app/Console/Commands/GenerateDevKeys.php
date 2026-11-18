<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateDevKeys extends Command
{
    protected $signature = 'voxelion:keys';

    protected $description = 'Generate the dev RS256 keypair used for OpinionPadi hand-off tokens';

    public function handle(): int
    {
        $dir = storage_path('app/keys');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $private = $dir.'/opinionpadi_private.pem';
        $public = $dir.'/opinionpadi_public.pem';

        if (is_file($private) && is_file($public)) {
            $this->info('Keypair already exists at storage/app/keys');

            return self::SUCCESS;
        }

        $config = ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048];

        // Windows PHP builds need an explicit openssl.cnf location.
        foreach (['C:/xampp/php/extras/openssl/openssl.cnf', 'C:/xampp/apache/conf/openssl.cnf'] as $cnf) {
            if (is_file($cnf)) {
                $config['config'] = $cnf;
                break;
            }
        }

        $res = openssl_pkey_new($config);
        if ($res === false) {
            $this->error('openssl_pkey_new failed: '.openssl_error_string());

            return self::FAILURE;
        }

        if (! openssl_pkey_export($res, $pem, null, $config)) {
            $this->error('openssl_pkey_export failed: '.openssl_error_string());

            return self::FAILURE;
        }

        file_put_contents($private, $pem);
        file_put_contents($public, openssl_pkey_get_details($res)['key']);

        $this->info('RS256 dev keypair written to storage/app/keys');

        return self::SUCCESS;
    }
}
