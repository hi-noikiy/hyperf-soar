<?php
declare(strict_types = 1);

namespace Wilbur\HyperfSoar\Aspect;

use Guanguans\SoarPHP\Exceptions\InvalidArgumentException;
use Guanguans\SoarPHP\Exceptions\InvalidConfigException;
use Hyperf\Config\Annotation\Value;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Utils\Context;
use Wilbur\HyperfSoar\Listener\QueryExecListener;
use Wilbur\HyperfSoar\SoarService;

/**
 * @Aspect
 */
class ResponseAspect extends AbstractAspect
{
	public $classes = [
		'Hyperf\HttpServer\Response::json',
	];

	/**
	 * @Value("soar.enabled")
	 * @var bool
	 */
	protected $soarIsEnabled;

	/**
	 * @Inject
	 * @var SoarService
	 */
	protected $soar;

	/**
	 * @param ProceedingJoinPoint $proceedingJoinPoint
	 *
	 * @return mixed
	 * @throws InvalidArgumentException
	 * @throws Exception
	 * @throws InvalidConfigException
	 */
	public function process(ProceedingJoinPoint $proceedingJoinPoint)
	{
		$sqlKey = \class_basename(QueryExecListener::class);

		if (!$this->soarIsEnabled || !Context::has($sqlKey)) {
			return $proceedingJoinPoint->process();
		}

		$eventSqlList = Context::get($sqlKey);
		$explains     = [];
		foreach ($eventSqlList as $sql) {
			$explains[] = $this->soar->score($sql);
		}

		$response = $proceedingJoinPoint->process();

		$oldBody = \json_decode($response->getBody()->getContents(), true);
		$newBody = \json_encode(\array_merge($oldBody, ['soar' => $explains]), \JSON_UNESCAPED_UNICODE);

		return $response->withBody(new SwooleStream($newBody));
	}
}