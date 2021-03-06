<?php

/*
 * This file is part of the FSi Component package.
 *
 * (c) Szczepan Cieslik <szczepan@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource\Tests\Driver\Doctrine;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use FSi\Component\DataSource\DataSourceFactory;
use FSi\Component\DataSource\DataSourceInterface;
use FSi\Component\DataSource\Driver\DriverFactoryManager;
use FSi\Component\DataSource\Tests\Fixtures\News;
use FSi\Component\DataSource\Tests\Fixtures\Category;
use FSi\Component\DataSource\Tests\Fixtures\Group;
use FSi\Component\DataSource\Tests\Fixtures\TestManagerRegistry;
use FSi\Component\DataSource\Driver\Doctrine\Extension\Core\CoreExtension;
use FSi\Component\DataSource\Driver\Doctrine\DoctrineFactory;
use FSi\Component\DataSource\Extension\Symfony\Form\FormExtension;
use FSi\Component\DataSource\Extension\Symfony;
use FSi\Component\DataSource\Extension\Core;
use FSi\Component\DataSource\Extension\Core\Ordering\OrderingExtension;
use Symfony\Component\Form;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension;
use FSi\Component\DataSource\Extension\Core\Pagination\PaginationExtension;

/**
 * Tests for Doctrine driver.
 */
class DoctrineDriverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        if (!class_exists('Doctrine\ORM\EntityManager')) {
            $this->markTestSkipped('Doctrine needed!');
        }

        // the connection configuration
        $dbParams = array(
            'driver' => 'pdo_sqlite',
            'memory' => true,
        );

        $config = Setup::createAnnotationMetadataConfiguration(array(FIXTURES_PATH), true, null, null, false);
        $em = EntityManager::create($dbParams, $config);
        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $classes = array(
            $em->getClassMetadata('FSi\Component\DataSource\Tests\Fixtures\News'),
            $em->getClassMetadata('FSi\Component\DataSource\Tests\Fixtures\Category'),
            $em->getClassMetadata('FSi\Component\DataSource\Tests\Fixtures\Group'),
        );
        $tool->createSchema($classes);
        $this->load($em);
        $this->em = $em;
    }

    /**
     * General test for DataSource wtih DoctrineDriver in basic configuration.
     */
    public function testGeneral()
    {
        $datasourceFactory = $this->getDataSourceFactory();
        $datasources = array();
        $driverOptions = array(
            'entity' => 'FSi\Component\DataSource\Tests\Fixtures\News',
        );

        $datasources[] = $datasourceFactory->createDataSource('doctrine', $driverOptions, 'datasource');
        $qb = $this->em->createQueryBuilder()
            ->select('n')
            ->from('FSi\Component\DataSource\Tests\Fixtures\News', 'n');

        $driverOptions = array(
            'qb' => $qb,
            'alias' => 'n'
        );
        $datasources[] = $datasourceFactory->createDataSource('doctrine', $driverOptions, 'datasource2');

        foreach ($datasources as $datasource) {
            $datasource
                ->addField('title', 'text', 'in')
                ->addField('author', 'text', 'like')
                ->addField('created', 'datetime', 'between', array(
                    'field' => 'create_date',
                ))
                ->addField('category', 'entity', 'eq')
                ->addField('category2', 'entity', 'isNull')
                ->addField('group', 'entity', 'memberof', array(
                    'field' => 'groups',
                ))
                ->addField('tags', 'text', 'isNull', array(
                    'field' => 'tags'
                ));

            $result1 = $datasource->getResult();
            $this->assertEquals(100, count($result1));
            $view1 = $datasource->createView();

            //Checking if result cache works.
            $this->assertSame($result1, $datasource->getResult());

            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::PARAMETER_FIELDS => array(
                        'author' => 'domain1.com',
                    ),
                ),
            );
            $datasource->bindParameters($parameters);
            $result2 = $datasource->getResult();

            //Checking cache.
            $this->assertSame($result2, $datasource->getResult());

            $this->assertEquals(50, count($result2));
            $this->assertNotSame($result1, $result2);
            unset($result1);
            unset($result2);

            $this->assertEquals($parameters, $datasource->getParameters());

            $datasource->setMaxResults(20);
            $parameters = array(
                $datasource->getName() => array(
                    PaginationExtension::PARAMETER_PAGE => 1,
                ),
            );

            $datasource->bindParameters($parameters);
            $result = $datasource->getResult();
            $this->assertEquals(100, count($result));
            $i = 0;
            foreach ($result as $item) {
                $i++;
            }

            $this->assertEquals(20, $i);

            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::PARAMETER_FIELDS => array(
                        'author' => 'domain1.com',
                        'title' => array('title44', 'title58'),
                        'created' => array('from' => new \DateTime(date("Y:m:d H:i:s", 35 * 24 * 60 * 60))),
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            $view = $datasource->createView();
            $result = $datasource->getResult();
            $this->assertEquals(2, count($result));

            //Checking entity fields. We assume that database was created so first category and first group have ids equal to 1.
            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::PARAMETER_FIELDS => array(
                        'group' => 1,
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            $result = $datasource->getResult();
            $this->assertEquals(25, count($result));

            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::PARAMETER_FIELDS => array(
                        'category' => 1,
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            $result = $datasource->getResult();
            $this->assertEquals(20, count($result));

            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::PARAMETER_FIELDS => array(
                        'group' => 1,
                        'category' => 1,
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            $result = $datasource->getResult();
            $this->assertEquals(5, count($result));

            //Checking sorting.
            $parameters = array(
                $datasource->getName() => array(
                    OrderingExtension::PARAMETER_SORT => array(
                        'title' => 'asc'
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            foreach ($datasource->getResult() as $news) {
                $this->assertEquals('title0', $news->getTitle());
                break;
            }

            //Checking sorting.
            $parameters = array(
                $datasource->getName() => array(
                    OrderingExtension::PARAMETER_SORT => array(
                        'title' => 'desc',
                        'author' => 'asc'
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            foreach ($datasource->getResult() as $news) {
                $this->assertEquals('title99', $news->getTitle());
                break;
            }

            //checking isnull & notnull
            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::PARAMETER_FIELDS => array(
                        'tags' => 'null'
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            $result1 = $datasource->getResult();
            $this->assertEquals(50, count($result1));
            $ids = array();

            foreach($result1 as $item) {
                $ids[] = $item->getId();
            }

            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::PARAMETER_FIELDS => array(
                        'tags' => 'notnull'
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            $result2 = $datasource->getResult();
            $this->assertEquals(50, count($result2));

            foreach($result2 as $item) {
                $this->assertTrue(!in_array($item->getId(),$ids));
            }

            unset($result1);
            unset($result2);

             $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::PARAMETER_FIELDS => array(
                        'category2' => 'null'
                    ),
                ),
            );

            //checking isnull & notnull - field type entity
            $datasource->bindParameters($parameters);
            $result1 = $datasource->getResult();
            $this->assertEquals(50, count($result1));
            $ids = array();

            foreach($result1 as $item) {
                $ids[] = $item->getId();
            }

            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::PARAMETER_FIELDS => array(
                        'category2' => 'notnull'
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            $result2 = $datasource->getResult();
            $this->assertEquals(50, count($result2));

            foreach($result2 as $item) {
                $this->assertTrue(!in_array($item->getId(),$ids));
            }

            unset($result1);
            unset($result2);

            //Test for clearing fields.
            $datasource->clearFields();
            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::PARAMETER_FIELDS => array(
                        'author' => 'domain1.com',
                    ),
                ),
            );

            //Since there are no fields now, we should have all of entities.
            $datasource->bindParameters($parameters);
            $result = $datasource->getResult();
            $this->assertEquals(100, count($result));
        }
    }

    /**
     * Checks DataSource wtih DoctrineDriver using more sophisticated QueryBuilder.
     */
    public function testQuery()
    {
        $dataSourceFactory = $this->getDataSourceFactory();

        $qb = $this->em->createQueryBuilder()
            ->select('n')
            ->from('FSi\Component\DataSource\Tests\Fixtures\News', 'n')
            ->join('n.category', 'c')
            ->join('n.groups', 'g')
        ;

        $driverOptions = array(
            'qb' => $qb,
            'alias' => 'n'
        );

        $datasource = $dataSourceFactory->createDataSource('doctrine', $driverOptions, 'datasource');
        $datasource->addField('author', 'text', 'like')
            ->addField('category', 'text', 'like', array(
                'field' => 'c.name',
            ))
            ->addField('group', 'text', 'like', array(
                'field' => 'g.name',
            ));

        $parameters = array(
            $datasource->getName() => array(
                DataSourceInterface::PARAMETER_FIELDS => array(
                    'group' => 'group0',
                ),
            ),
        );

        $datasource->bindParameters($parameters);
        $this->assertEquals(25, count($datasource->getResult()));

        $parameters = array(
            $datasource->getName() => array(
                DataSourceInterface::PARAMETER_FIELDS => array(
                    'group' => 'group',
                ),
            ),
        );

        $datasource->bindParameters($parameters);
        $this->assertEquals(100, count($datasource->getResult()));

        $parameters = array(
            $datasource->getName() => array(
                DataSourceInterface::PARAMETER_FIELDS => array(
                    'group' => 'group0',
                    'category' => 'category0',
                ),
            ),
        );

        $datasource->bindParameters($parameters);
        $this->assertEquals(5, count($datasource->getResult()));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testCreateDriverWithoutEntityAndQbOptions()
    {
        $factory = $this->getDoctrineFactory();
        $factory->createDriver(array());
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        unset($this->em);
    }

    /**
     * Return configured DoctrinFactory.
     *
     * @return DoctrineFactory.
     */
    private function getDoctrineFactory()
    {
        $extensions = array(
            new CoreExtension(),
        );

        return new DoctrineFactory(new TestManagerRegistry($this->em), $extensions);
    }

    /**
     * Return configured DataSourceFactory.
     *
     * @return \FSi\Component\DataSource\DataSourceFactory
     */
    private function getDataSourceFactory()
    {
        $driverFactoryManager = new DriverFactoryManager(array(
            $this->getDoctrineFactory()
        ));

        $extensions = array(
            new Symfony\Core\CoreExtension(),
            new Core\Pagination\PaginationExtension(),
            new OrderingExtension(),
        );

        return new DataSourceFactory($driverFactoryManager, $extensions);
    }

    /**
     * @param EntityManager $em
     */
    private function load(EntityManager $em)
    {
        //Injects 5 categories.
        $categories = array();
        for ($i = 0; $i < 5; $i++) {
            $category = new Category();
            $category->setName('category'.$i);
            $em->persist($category);
            $categories[] = $category;
        }

        //Injects 4 groups.
        $groups = array();
        for ($i = 0; $i < 4; $i++) {
            $group = new Group();
            $group->setName('group'.$i);
            $em->persist($group);
            $groups[] = $group;
        }

        //Injects 100 newses.
        for ($i = 0; $i < 100; $i++) {
            $news = new News();
            $news->setTitle('title'.$i);

            //Half of entities will have different author and content.
            if ($i % 2 == 0) {
                $news->setAuthor('author'.$i.'@domain1.com');
                $news->setShortContent('Lorem ipsum.');
                $news->setContent('Content lorem ipsum.');
                $news->setTags('lorem ipsum');
                $news->setCategory2($categories[($i + 1) % 5]);
            } else {
                $news->setAuthor('author'.$i.'@domain2.com');
                $news->setShortContent('Dolor sit amet.');
                $news->setContent('Content dolor sit amet.');
            }

            //Each entity has different date of creation and one of four hours of creation.
            $createDate = new \DateTime(date("Y:m:d H:i:s", $i * 24 * 60 * 60));
            $createTime = new \DateTime(date("H:i:s", (($i % 4) + 1 ) * 60 * 60));

            $news->setCreateDate($createDate);
            $news->setCreateTime($createTime);

            $news->setCategory($categories[$i % 5]);
            $news->getGroups()->add($groups[$i % 4]);

            $em->persist($news);
        }

        $em->flush();
    }
}