<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\DeliveryRepository;
use App\Repositories\RrhhRepository;
use App\Support\Database;
use InvalidArgumentException;
use Throwable;

final class PeopleController
{
    private RrhhRepository $repository;
    private DeliveryRepository $deliveries;

    public function __construct(Database $database)
    {
        $this->repository = new RrhhRepository($database);
        $this->deliveries = new DeliveryRepository($database->connection('sindesg'));
    }

    public function handle(string $action, array $query): array
    {
        try {
            if ($action === 'show') {
                $employeeId = (int) ($query['id'] ?? 0);
                $person = $this->repository->findPerson($employeeId);

                if ($person === null) {
                    throw new InvalidArgumentException('El funcionario solicitado no existe en RRHH2029.');
                }

                return [
                    'view' => SINDESG_ROOT . '/app/Views/people/show.php',
                    'title' => trim((string) $person['first_name'] . ' ' . (string) $person['last_name']),
                    'data' => [
                        'person' => $person,
                        'deliveryHistory' => $this->deliveries->historySummaryForEmployee(
                            $employeeId,
                            12
                        ),
                    ],
                ];
            }

            if ($action === 'unit') {
                $unitId = (int) ($query['id'] ?? 0);
                $unit = $this->repository->findUnit($unitId);

                if ($unit === null) {
                    throw new InvalidArgumentException('La unidad organizacional solicitada no existe en RRHH2029.');
                }

                return [
                    'view' => SINDESG_ROOT . '/app/Views/people/unit.php',
                    'title' => (string) $unit['official_name'],
                    'data' => [
                        'unit' => $unit,
                        'childUnits' => $this->repository->childUnits($unitId),
                        'unitPeople' => $this->repository->peopleByUnit($unitId),
                    ],
                ];
            }

            $searchQuery = trim((string) ($query['q'] ?? ''));

            return [
                'view' => SINDESG_ROOT . '/app/Views/people/index.php',
                'title' => 'Funcionarios',
                'data' => [
                    'rrhhStats' => $this->repository->stats(),
                    'searchQuery' => $searchQuery,
                    'peopleResults' => $searchQuery !== ''
                        ? $this->repository->searchPeople($searchQuery)
                        : [],
                    'rootUnits' => $this->repository->rootUnits(),
                ],
            ];
        } catch (InvalidArgumentException $exception) {
            return [
                'view' => SINDESG_ROOT . '/app/Views/people/unavailable.php',
                'title' => 'Funcionarios',
                'data' => ['peopleError' => $exception->getMessage()],
            ];
        } catch (Throwable $exception) {
            return [
                'view' => SINDESG_ROOT . '/app/Views/people/unavailable.php',
                'title' => 'Funcionarios',
                'data' => [
                    'peopleError' => 'No fue posible consultar RRHH2029. La fuente maestra no fue modificada.',
                ],
            ];
        }
    }
}
