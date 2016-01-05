<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Composer\Console\Application;
use Composer\IO\ConsoleIO;
use Symfony\Component\Console\Helper\HelperSet;
use Composer\Factory;
use Composer\Repository\CompositeRepository;

use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Composer\DependencyResolver\Pool;
use Cache;
use Storage;

class ProxyController extends Controller
{
    public function generate()
    {
        $packages = [
            'packages' => [],
            'notify' => 'https://packagist.org/downloads/%package%',
            'notify-batch' => 'https://packagist.org/downloads/',
            'providers-url' => '/p/%package%$%hash%.json',
            'search' => 'https://packagist.org/search.json?q=%query%',
            'provider-includes' => [
            ],

            'sync-time' => date('c'),
        ];

        $repos = $this->getRepos();
        foreach ($repos as $repo) {

            $repoConfig = $repo->getRepoConfig();
            $repoName = $repo->name;
            $repo->getProviderNames();
            // do some hack
            $ref = new \ReflectionProperty($repo, 'providerListing');
            $ref->setAccessible(true);
            $all = $ref->getValue($repo);

            $json = json_encode(['providers' => $all]);
            $sha256 = hash('sha256', $json);
            $file = "p/{$repoName}/all\${$sha256}.json";
            Storage::put($file, $json);

            $all['provider-includes'][$file] = ['sha256' => $sha256];
        }
        Storage::put("packages.json", json_encode($packages));

    }

    public function package($info)
    {

    }

    protected function getRepos()
    {
        $input = new StringInput('');
        $output = new BufferedOutput;
        $helperSet = new HelperSet;

        $io = new ConsoleIO($input, $output, $helperSet);

        putenv('COMPOSER=../composer.json');

        $composer = Factory::create($io);
        $config = $composer->getConfig();
        $repos = $config->getRepositories();
        foreach ($repos as &$repo) {
            $type = ucfirst($repo['type']);
            $type = "Composer\\Repository\\{$type}Repository";
            $repo = new $type($repo, $io, $config);
            $repo->name = preg_replace(['{^https?://}i', '{[^a-z0-9._]}i'], ['', '-'], $repo->getRepoConfig()['url']);
        }
        return $repos;
    }

}
