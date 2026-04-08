<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Core\Gitlab;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GitlabClient
{
    private HttpClientInterface $client;
    private string $deployTokenUser;
    private string $deployTokenPassword;

    public function __construct()
    {
        $this->deployTokenUser = $_ENV['GITLAB_DEPLOY_TOKEN_USER'];
        $this->deployTokenPassword = $_ENV['GITLAB_DEPLOY_TOKEN_PASSWORD'];

        $options = new HttpOptions();
        $options->setHeader('PRIVATE-TOKEN', $_ENV['GITLAB_API_TOKEN'] ?? '');

        $this->client = HttpClient::create()->withOptions($options->toArray());
    }

    public function getLatestRelease(): ?array
    {
        $response = $this->client->request('GET', 'https://gitlab.com/api/v4/projects/78182343/releases');

        return $response->toArray(false)[0] ?? null;
    }

    public function getLatestTag(): ?string
    {
        $latestRelease = $this->getLatestRelease();

        if (isset($latestRelease['tag_name'])) {
            return $latestRelease['tag_name'];
        }

        return null;
    }

    public function getLatestPackageUrl(string $platform, string $architecture): ?string
    {
        $filename = sprintf('%s-%s.tar.gz', $platform, $architecture);

        $latestRelease = $this->getLatestRelease();

        foreach ($latestRelease['assets']['links'] ?? [] as $asset) {
            if (isset($asset['name']) && $asset['name'] === $filename) {
                return $asset['url'] ? $this->authenticateGitlabUrl($asset['url']) : null;
            }
        }

        return null;
    }

    private function authenticateGitlabUrl(string $url): string
    {
        return preg_replace('/^https:\/\//', 'https://'.$this->deployTokenUser.':'.$this->deployTokenPassword.'@', $url);
    }
}
