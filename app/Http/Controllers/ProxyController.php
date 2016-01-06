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

            $packages['provider-includes'][$file] = ['sha256' => $sha256];
        }
        Storage::put("packages.json", json_encode($packages));
        return $packages;

    }

    public function package($info)
    {

        preg_match('{^(?<name>.*)\$(?<hash>.*)\.json$}i', $info, $matches);
        $hash = $matches['hash'];
        $name = $matches['name'];
        $filename = 'p/hash/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . hash('sha256', $hash . $name) . '.json';
        if (!Storage::has($filename)) {
            $repos = $this->getRepos();
            $installedRepo = new CompositeRepository($repos);
            $pool = new Pool('dev');
            $pool->addRepository($installedRepo);
            $matches = $pool->whatProvides($name, null);
            if (!$matches) {
                return '{}';
            } else {
                $match = $matches[0];
                $repo = $match->getRepository();
                $ref = new \ReflectionProperty($repo, 'providersUrl');
                $ref->setAccessible(true);
                $providersUrl = $ref->getValue($repo);

                $ref = new \ReflectionProperty($repo, 'cache');
                $ref->setAccessible(true);
                $cache = $ref->getValue($repo);

                $url = str_replace(array('%package%', '%hash%'), array($name, $hash), $providersUrl);
                $cacheKey = 'provider-' . strtr($name, '/', '$') . '.json';
                if ($cache->sha256($cacheKey) === $hash) {
                    $packages = $cache->read($cacheKey);
                }
                if (!isset($packages) && empty($packages)) {
                    throw new Exception("Cache should exists, please report this issue on github", 1);
                }
                Storage::put($filename, $packages);
            }

        }
        return Storage::get($filename);

    }

    protected function getRepos()
    {
        $input = new StringInput('');
        $output = new BufferedOutput;
        $helperSet = new HelperSet;

        $io = new ConsoleIO($input, $output, $helperSet);
        if(!file_exists('composer.json')) {
            putenv('COMPOSER=../composer.json');
        }

        $composer = Factory::create($io);
        $config = $composer->getConfig();
        $repos = $config->getRepositories();


        foreach ($repos as &$repo) {
            $type = ucfirst($repo['type']);
            $type = "Composer\\Repository\\{$type}Repository";
            $repo = new $type($repo, $io, $config);
            $ref = new \ReflectionProperty($repo, 'url');
            $ref->setAccessible(true);
            $url = $ref->getValue($repo);
            $repo->name = preg_replace(['{^https?://}i', '{[^a-z0-9._]}i'], ['', '-'], $url);
        }
        return $repos;
    }

}
