<template>
    <v-app>
        <v-main>
            <!-- <v-tabs  style="height: 50px">
                    <inertia-link  as="v-tab" href="/" tag="inertia-link"  :exact="true">Заявки</inertia-link>
                    <inertia-link  as="v-tab" href="/services" tag="inertia-link"  :exact="true">Услуги</inertia-link>
            </v-tabs>-->

            <v-container fluid style="height: calc(100vh - 50px);">
                <user-menu></user-menu>
                <slot/>
            </v-container>
        </v-main>
    </v-app>
</template>

<script>

import UserMenu
    from "../User/UserMenu";

export default {
    name: "GlobalLayout",
    components: {UserMenu},
    data: () => ({
        drawer: true,
        links: [
            {title: 'Главная', to: '/'}
        ]
    })
}
</script>

<!--suppress CssUnusedSymbol -->
<style>
.v-alert__border{
    border-width: 1px !important;
}
.v-main__wrap{
    display: flex;
    flex-direction: column;
    height: 100vh;
}
</style>
