<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncMagentoOrdersCommand extends Command
{
    protected $signature = 'magento:sync-orders {--email= : Sincroniza solo el correo indicado}';

    protected $description = 'Compatibilidad: las compras Magento ahora se revisan manualmente en backoffice.';

    public function handle(): int
    {
        $this->info('La acreditacion automatica de Magento esta deshabilitada. Revisa las solicitudes en el backoffice.');

        return self::SUCCESS;
    }
}
