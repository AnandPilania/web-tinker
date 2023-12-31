<?php

namespace AnandPilania\WebTinker;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Psy\ExecutionClosure;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class Executor
{
    use Support;

    public function __invoke(Request $request): View|ViewFactory|JsonResponse
    {
        if ($request->isMethod('GET')) {
            return view('web-tinker::index');
        }

        chdir(base_path());

        $status = 1;
        $app = app();
        $shell = $this->setContext($app)->getShell();

        $output = $this->setOutput();
        $output->setDecorated(true);

        $shell->setOutput($output);

        $code = $request->input('code');

        if (blank($code)) {
            return throw new \Exception('Missing CODE for execution!');
        }

        try {
            $shell->addCode(
                $this->clearCode($code)
            );

            $closure = new ExecutionClosure($shell);
            $closure->execute();

            $response = preg_replace('/\\x1b\\[\\d+m/', '', $output->fetch());
        } catch (\Throwable $e) {
            $status = 0;
            $response = $e->getMessage();
        }

        return response()->json([
            'status' => $status,
            'response' => $this->dump($response),
        ]);
    }

    protected function clearCode($code): string
    {
        $cleanCode = '';

        if (strncmp($code, '<?php', 5) === 0) {
            $code = array_reverse(explode('<?php', $code, 2))[0];
        }

        foreach (token_get_all('<?php ' . $code) as $token) {
            if (is_string($token)) {
                $cleanCode .= $token;

                continue;
            }

            $cleanCode .= in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : $token[1];
        }

        if (strncmp($cleanCode, '<?php', 5) === 0) {
            $cleanCode = array_reverse(explode('<?php', $cleanCode, 2))[0];
        }

        return trim($cleanCode);
    }

    protected function dump(mixed $arguments, int $maxDepth = null): mixed
    {
        if (is_null($arguments)) {
            return null;
        }

        if (is_string($arguments)) {
            return $arguments;
        }

        if (is_int($arguments)) {
            return $arguments;
        }

        if (is_bool($arguments)) {
            return $arguments;
        }

        $varCloner = new VarCloner();

        $dumper = new HtmlDumper();

        if ($maxDepth !== null) {
            $dumper->setDisplayOptions([
                'maxDepth' => $maxDepth,
            ]);
        }

        $htmlDumper = (string)$dumper->dump($varCloner->cloneVar($arguments), true);

        return Str::cut($htmlDumper, '<pre ', '</pre>');
    }

    private function setOutput(): BufferedOutput
    {
        return new BufferedOutput();
    }
}
