<?php

namespace App\Controllers;

/** Reportes por grupo y asignatura (documento maestro 4.8), con exportación a CSV. */
class ReportsController extends BaseController
{
    public function index(?int $offeringId = null)
    {
        $rep       = service('reports');
        $offerings = $rep->offerings();
        if (! $offerings) {
            return view('reports/empty', $this->layoutData() + ['title' => 'Reportes']);
        }
        $offeringId ??= (int) $this->request->getGet('o') ?: $rep->defaultOffering($offerings);
        $report     = $rep->forOffering($offeringId);
        if (! $report) {
            return redirect()->to('reportes')->with('toast', ['type' => 'error', 'msg' => 'Ese grupo no existe o no puede ver sus reportes.']);
        }

        return view('reports/group', $this->layoutData() + ['title' => 'Reportes', 'r' => $report, 'offerings' => $offerings]);
    }

    public function export(int $offeringId)
    {
        $report = service('reports')->forOffering($offeringId);
        if (! $report) {
            return redirect()->to('reportes')->with('toast', ['type' => 'error', 'msg' => 'Ese grupo no existe o no puede ver sus reportes.']);
        }
        helper(['text', 'url']);
        $name = url_title(convert_accented_characters($report['offering']['subject'] . ' ' . $report['offering']['group']), '-', true);

        [$head, $rows] = service('reports')->csvTable($report);

        return $this->csvResponse('notas-' . $name . '-' . date('Y-m-d') . '.csv', $head, $rows);
    }
}
