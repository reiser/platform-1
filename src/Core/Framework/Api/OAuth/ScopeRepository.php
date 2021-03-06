<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\OAuth\Scope\AdminScope;
use Shopware\Core\Framework\Api\OAuth\Scope\WriteScope;

class ScopeRepository implements ScopeRepositoryInterface
{
    /**
     * @var ScopeEntityInterface[]
     */
    private $scopes;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param ScopeEntityInterface[] $scopes
     */
    public function __construct(iterable $scopes, Connection $connection)
    {
        $this->connection = $connection;
        $scopeIndex = [];
        foreach ($scopes as $scope) {
            $scopeIndex[$scope->getIdentifier()] = $scope;
        }

        $this->scopes = $scopeIndex;
    }

    /**
     * {@inheritdoc}
     */
    public function getScopeEntityByIdentifier($identifier): ScopeEntityInterface
    {
        return $this->scopes[$identifier] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null
    ): array {
        $hasWrite = false;

        if ($grantType === 'password') {
            $hasWrite = true;
        }

        if ($grantType === 'client_credentials' && $clientEntity instanceof ApiClient && $clientEntity->getWriteAccess()) {
            $hasWrite = true;
        }

        if (!$hasWrite) {
            foreach ($scopes as $index => $scope) {
                if ($scope instanceof WriteScope) {
                    unset($scopes[$index]);
                }
            }
        }

        if ($hasWrite) {
            $scopes[] = new WriteScope();
        }

        $isAdmin = $this->connection->createQueryBuilder()
            ->select('admin')
            ->from('user')
            ->where('id = UNHEX(:accessKey)')
            ->setParameter('accessKey', $userIdentifier)
            ->setMaxResults(1)
            ->execute()
            ->fetchColumn();

        if ($isAdmin) {
            $scopes[] = new AdminScope();
        }

        return $scopes;
    }
}
