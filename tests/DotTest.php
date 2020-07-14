<?php

use PHPUnit\Framework\TestCase;
use fpoirotte\EnumTrait;

class DotTest extends TestCase
{
    static protected function exec_command($cmd, string $stdin = '')
    {
        $spec = array(
           0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
           1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
        );
        $proc = proc_open($cmd, $spec, $pipes);
        if ($proc === false) {
            throw new \RuntimeException('Could not create subprocess');
        }

        while (strlen($stdin)) {
            $len = fwrite($pipes[0], $stdin);
            if ($len === 0) {
                break;
            }
            $stdin = (string) substr($stdin, $len);
        }
        fclose($pipes[0]);

        $stdout = array();
        while (true) {
            $data = fread($pipes[1], 8192);
            if ($data === false || $data === '') {
                break;
            }
            $stdout[] = $data;
        }
        fclose($pipes[1]);
        proc_terminate($proc, 9);
        return implode('', $stdout);
    }

    public function dotFiles()
    {
        $it = new \GlobIterator('tests' . DIRECTORY_SEPARATOR . 'testdata' . DIRECTORY_SEPARATOR. '*.dot',
                                \FilesystemIterator::CURRENT_AS_PATHNAME);
        foreach ($it as $file) {
            yield [$file];
        }
    }

    /**
     * @dataProvider dotFiles
     */
    public function testParseAndExport($filename)
    {
        $graph = (string) \fpoirotte\DotGraph\Parser::parse(file_get_contents($filename));
        $exported = self::exec_command('dot -Tsvg', $graph);
        $original = self::exec_command('dot -Tsvg ' . escapeshellarg($filename));
        $this->assertXmlStringEqualsXmlString($original, $exported);
    }
}
