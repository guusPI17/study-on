<?php

namespace App\Tests\Mock;

use App\DTO\Response as ResponseDto;
use App\DTO\User as UserDto;
use App\Exception\FailureResponseException;
use App\Service\BillingClient;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BillingClientMock extends BillingClient
{
    private $security;
    private $arrUsers;
    private $serializer;

    public function __construct(
        string $billingUrlBase,
        string $billingApiVersion,
        HttpClientInterface $httpClient,
        SerializerInterface $serializer,
        Security $security
    ) {
        parent::__construct($billingUrlBase, $billingApiVersion, $httpClient, $serializer, $security);

        $user = new UserDto();
        $user->setUsername('user@test.com');
        $user->setPassword('user@test.com');
        $user->setToken('eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE2MTY2ODA4MDIsImV4cCI6MTYxNjY4NDQwMiwicm9sZXMiOlsiUk9MRV9VU0VSIl0sInVzZXJuYW1lIjoidXNlckB0ZXN0LmNvbSJ9.TQRA5WYRea4AhbL93af3w701F0VVGYf1QIlXcxR9zdxDY2oARCLE53tHyXLkGaCNp7QAk4PrmjJn-MArdZBuyqUrWFXs5kcvnHs5ulNxvM12K71rrf-gC6W1BBJafaFhkcJ1ZJWX4mvfNqGYMhv39GqNJDvrR3FKL8_mtKYk61ENXyyddxpbTEJL41Brh-yXRlwcyW_rXtozWTw2sRBxxEgBcEqLDeZu70aeaqGsysr5z66uN08IaEkLgrROPVyeTGJdSweuwvrWISomaReLQdYCacjajTE5eeEMZwlIQ_zVNEtz0V6d-TsTjsbVW5GlRZViWl5RRp3-YEv4V1NzWBTZyySefhUexjDBSyyU_HUYNF1sLdg-VLbBoJ0SLWbqkdkjkBPYCkX_b7zzw-z1eTKsG62rsBJXGlbe02FlJTleNbPXxOcMnuq_yiSSQYiiTtbXHEp1Gs8J5o6OXUjQJQZk0uWz5LXwSaBpTnxjGhfAwc7Mv6XmreOBeCtk_niMDNwO6qS3Z280tUQY23IYvs6dlUiDqGOcJ6PTjE_Fi7ORIcW2jRbGvkx7iBtZybWzd1jRS8ZiWo5sNGgi3vzBXnMZOE5dXbXdm1851qxdWYg-04XJsTX6tT_CPDM9Dd-DEYBvx06wlE6AprLjpFH5ILYqI2WGi2lonCSH-V3j4tU');
        $user->setRoles(['ROLE_USER']);

        $admin = new UserDto();
        $admin->setUsername('admin@test.com');
        $admin->setPassword('admin@test.com');
        $admin->setToken('eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE2MTY2ODEzMTEsImV4cCI6MTYxNjY4NDkxMSwicm9sZXMiOlsiUk9MRV9TVVBFUl9BRE1JTiIsIlJPTEVfVVNFUiJdLCJ1c2VybmFtZSI6ImFkbWluQHRlc3QuY29tIn0.Rf4Em56X66OnCr1y7Zm7_vU7ibtkw6yc94Fz-gOArVKu-FHjCgNYt0PbDqY6DIWGFPwdyiVSZy_bhSbHiYhppAkYavAXtTRTppSi_JZmczJMhEEsNbDhVB367TE_YH4J4on91wUgFkcK_9jMNSn3xTGf-iG2agQlQYQ9NuYVgf1hsnX4dvFcAlZ52yQCfvl_nULNmHYU_H3TlmDvH5eY57Bp3FZYid7HCYkQkD1ypQCuO3yFFKUoJ9evs6HGnh_p0ZLEBF4Uk0sT890wUYwrNtEh44hQy9wOuo9PaPihctcinJskFCChWCI2MEAOpfzstd7JXIVCPThUT7ztCXmgi31-bGLoQaSAail9n3OPo8nB1ONM7gspqIMGvC-1BeZM8Oh3ym5PVfwdq3_r6Re5Co2lvLUQTcl1XDqU-EHyP0vsEXmCvwP-nHtWE9Xx5qyPVM-lNUdIjKdE8QjZf3T80NAwWZyDB4RLg2azgeyWf7av7K_P_zbWOXS0QIz2mZETfc74SX1o7j-3GNZt0bi9P1zVH_kv9gnZ_iqOzpygCQpWyUnEl7nBkpEITbh5yyY3jODoEQXcstK8CHupxfQpRYSWJXwr9y9UfGGWp4rkMF5QvEnRv-FjDBgz9Jui1Ih8Mn1NYJAhgeWgX1232uJ3PHjYM52HDKHqzbIK1_Z8Efo');
        $admin->setRoles(['ROLE_SUPER_ADMIN']);

        $this->arrUsers = [$user, $admin];

        $this->security = $security;
        $this->serializer = $serializer;
    }

    public function authorization(UserDto $dataUser): UserDto
    {
        /** @var UserDto $user */
        foreach ($this->arrUsers as $user) {
            if ($user->getUsername() == $dataUser->getUsername()
                && $user->getPassword() == $dataUser->getPassword()
            ) {
                return $user;
            }
        }
        $responseDto = new ResponseDto();
        $responseDto->setCode(401);
        $responseDto->setMessage('Invalid credentials.');
        throw new FailureResponseException($responseDto);
    }
}
