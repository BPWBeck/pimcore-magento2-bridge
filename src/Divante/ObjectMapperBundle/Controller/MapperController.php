<?php
/**
 * @category    pimcore5-module-magento2-integration
 * @date        26/03/2018
 * @author      Michał Bolka <mbolka@divante.co>
 * @copyright   Copyright (c) 2018 DIVANTE (https://divante.co)
 */
namespace Divante\ObjectMapperBundle\Controller;

use Divante\MagentoIntegrationBundle\Interfaces\MapperInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use Pimcore\Model\DataObject;
use Divante\MagentoIntegrationBundle\Model\Mapping\FromColumn;
use Divante\MagentoIntegrationBundle\Model\Mapping\ToColumn;
use Divante\ObjectMapperBundle\Service\MapperService;

/**
 * Class MapperController
 * @package Divante\ObjectMapperBundle\Controller
 */
class MapperController extends AdminController
{
    /** @var MapperInterface */
    protected $mapperService;

    /**
     * @return MapperInterface|object
     */
    public function getMapperService(): MapperInterface
    {
        if (!$this->mapperService instanceof MapperInterface) {
            $this->mapperService = $this->container->get(MapperService::class);
        }
        return $this->mapperService;
    }
    /**
     * @Method("GET")
     * @param Request $request
     * @Route("/get-columns-product")
     * @return JsonResponse
     */
    public function getColumnsProductAction(Request $request): JsonResponse
    {
        return $this->json($this->getColumnsForClass($request->get('configurationId'), 'product'));
    }

    /**
     * @Method("GET")
     * @param Request $request
     * @Route("/get-columns-category")
     * @return JsonResponse
     */
    public function getColumnsCategoryAction(Request $request): JsonResponse
    {
        return $this->json($this->getColumnsForClass($request->get('configurationId'), 'category'));
    }

    /**
     * @param        $configurationId
     * @param string $className
     * @return array
     */
    protected function getColumnsForClass($configurationId, string $className): array
    {
        $returnValue = ['success' => false];
        $definition = null;
        try {
            $configuration = DataObject\IntegrationConfiguration::getById($configurationId);
            $classAttribute = $className . 'Class';
            if (!($configuration->get($classAttribute))) {
                throw new \InvalidArgumentException();
            }
            $method  = 'get' . ucfirst($classAttribute);
            $definition = DataObject\ClassDefinition::getById($configuration->{$method}());
            if ($definition instanceof DataObject\ClassDefinition) {
                $mappingAttribute      = 'get' . ucfirst($className) . 'Mapping';
                $returnValue           = $this->getDataForClassDefinition(
                    $definition,
                    $configuration->{$mappingAttribute}()
                );
                $emptyValue            = new ToColumn();
                $emptyValue->fieldtype = 'input';
            }
        } catch (\Exception $exception) {
            die(var_dump($exception));
        }
        return $returnValue;
    }

    /**
     * @param $definition
     * @param $standardStructure
     * @return array
     * @throws \Exception
     */
    protected function getDataForClassDefinition($definition, $standardStructure): array
    {
        if (!$definition instanceof DataObject\ClassDefinition) {
            return ['success' => false];
        }
        $mapperService = $this->getMapperService();
        $fromColumns = $mapperService->getClassDefinitionForFieldSelection($definition);
        $standardStructure =  array_filter($standardStructure, function ($elem) {
            return $elem[1] != null;
        });
        $toColumns = array_map([$this, 'getToObjectForMapElement'], $standardStructure);
        array_push($fromColumns, $this->getEmptyFromColumnValue());
        $mapping = array_map([$this, 'getMappingForMapElement'], $standardStructure);

        return [
            'success'          => true,
            'mapping'          => $mapping,
            'fromColumns'      => $fromColumns,
            'toColumns'        => $toColumns,
            'bricks'           => [],
            'fieldcollections' => []
        ];
    }

    /**
     * @param $mapElement
     * @return mixed
     */
    protected function getToObjectForMapElement($mapElement)
    {
        if (!$mapElement[1]) {
            return null;
        }
        $requiredFields = $this->getMapperService()->getRequiredFields();
        $obj = new ToColumn();
        $obj->setLabel($mapElement[1]);
        $obj->setIdentifier($mapElement[1]);
        $obj->setFieldtype('input');
        $obj->setConfig(['required' => in_array($mapElement[1], $requiredFields)]);
        return $obj;
    }

    /**
     * @param $mapElement
     * @return array
     */
    protected function getMappingForMapElement($mapElement)
    {
        if (!$mapElement[1]) {
            return null;
        }

        return [
            'config' => null,
            'fromColumn' => $mapElement[0] ?? null,
            'identifier' => $mapElement[0] ?? null,
            'interpreterConfig' => null,
            'primaryIdentifier' => false,
            'setter' => null,
            'setterConfig' => null,
            'toColumn' => $mapElement[1]
        ];
    }

    /**
     * @return FromColumn
     */
    protected function getEmptyFromColumnValue(): FromColumn
    {
        $emptyValue = new FromColumn();
        $emptyValue->setLabel('(Empty)');
        $emptyValue->setIdentifier('');
        return $emptyValue;
    }
}
