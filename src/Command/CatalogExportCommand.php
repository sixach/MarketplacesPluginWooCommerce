<?php

namespace EffectConnect\Marketplaces\Command;

use EffectConnect\Marketplaces\Api\ProductHandler;
use EffectConnect\Marketplaces\Model\ConnectionCollection;
use EffectConnect\Marketplaces\Model\ConnectionResource;
use Exception;

class CatalogExportCommand
{
    private $catalogHandler;

    /**
     * CatalogExportCommand constructor.
     * @throws Exception
     */
    public function __construct()
    {
        $connections = ConnectionCollection::getActive();

        if (count($connections) > 0) {
            $this->catalogHandler = new ProductHandler();

            foreach($connections as $connection) {
                $this->execute($connection);
            }
        }
    }

    /**
     * Starts catalog export.
     * @param ConnectionResource $connection
     */
    protected function execute(ConnectionResource $connection)
    {
        try {
            $this->catalogHandler->catalogExport($connection);
        } catch (Exception $e) {}
    }
}