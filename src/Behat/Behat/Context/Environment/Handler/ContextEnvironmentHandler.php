<?php

/*
 * This file is part of the Behat.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Behat\Context\Environment\Handler;

use Behat\Behat\Context\ContextClass\ClassResolver;
use Behat\Behat\Context\ContextFactory;
use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Context\Environment\UninitializedContextEnvironment;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Environment\Handler\EnvironmentHandler;
use Behat\Testwork\Suite\Suite;

/**
 * Context-based environment handler.
 *
 * Handles build and initialisation of context-based environments using registered context initializers.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class ContextEnvironmentHandler implements EnvironmentHandler
{
    /**
     * @var ContextFactory
     */
    private $factory;
    /**
     * @var ClassResolver[]
     */
    private $classResolvers = array();

    /**
     * Initializes handler.
     *
     * @param ContextFactory $factory
     */
    public function __construct(ContextFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Registers context class resolver.
     *
     * @param ClassResolver $resolver
     */
    public function registerClassResolver(ClassResolver $resolver)
    {
        $this->classResolvers[] = $resolver;
    }

    /**
     * Checks if handler supports provided suite.
     *
     * @param Suite $suite
     *
     * @return Boolean
     */
    public function supportsSuite(Suite $suite)
    {
        return $suite->hasSetting('contexts') && is_array($suite->getSetting('contexts'));
    }

    /**
     * Builds environment object based on provided suite.
     *
     * @param Suite $suite
     *
     * @return UninitializedContextEnvironment
     */
    public function buildEnvironment(Suite $suite)
    {
        $environment = new UninitializedContextEnvironment($suite);
        foreach ($this->getNormalizedContextSettings($suite) as $context) {
            $environment->registerContextClass($this->resolveClass($context[0]), $context[1]);
        }

        return $environment;
    }

    /**
     * Checks if handler supports provided environment.
     *
     * @param Environment $environment
     * @param mixed       $testSubject
     *
     * @return Boolean
     */
    public function supportsEnvironmentAndSubject(Environment $environment, $testSubject = null)
    {
        return $environment instanceof UninitializedContextEnvironment;
    }

    /**
     * Isolates provided environment.
     *
     * @param UninitializedContextEnvironment $uninitializedEnvironment
     * @param mixed                           $testSubject
     *
     * @return InitializedContextEnvironment
     */
    public function isolateEnvironment(Environment $uninitializedEnvironment, $testSubject = null)
    {
        $environment = new InitializedContextEnvironment($uninitializedEnvironment->getSuite());
        foreach ($uninitializedEnvironment->getContextClassesWithArguments() as $class => $arguments) {
            $context = $this->factory->createContext($class, $arguments);
            $environment->registerContext($context);
        }

        return $environment;
    }

    /**
     * Returns normalized suite context settings.
     *
     * @param Suite $suite
     *
     * @return array
     */
    private function getNormalizedContextSettings(Suite $suite)
    {
        return array_map(
            function ($context) {
                $class = $context;
                $arguments = array();

                if (is_array($context)) {
                    $class = current(array_keys($context));
                    $arguments = $context[$class];
                }

                return array($class, $arguments);
            },
            $suite->getSetting('contexts')
        );
    }

    /**
     * Resolves class using registered class resolvers.
     *
     * @param string $class
     *
     * @return string
     */
    private function resolveClass($class)
    {
        foreach ($this->classResolvers as $resolver) {
            if ($resolver->supportsClass($class)) {
                return $resolver->resolveClass($class);
            }
        }

        return $class;
    }
}
