<template>
            <v-navigation-drawer
                permanent
                expand-on-hover
            >
                <v-list>
                    <v-list-item>
                        <v-list-item-content>
                            <v-list-item-title class="text-h6">
                                {{ this.$data.userData['fio'] }}
                            </v-list-item-title>
                            <v-list-item-subtitle>{{ this.$data.userData['email'] }}</v-list-item-subtitle>
                        </v-list-item-content>
                    </v-list-item>
                </v-list>

                <v-divider></v-divider>

                <v-list
                    nav
                    dense
                >
                    <v-list-item-group>
                        <v-list-item
                            v-for="(item, i) in items"
                            :key="i"
                            link
                            class="pl-0"
                        >
                            <v-list-item-title>
                                <a class="v-list-item" :href="item.href" v-on:click.stop.prevent="linkToOtherWindow(item.href, item.target)"><v-icon class="mr-4">{{item.icon}}</v-icon> {{item.text}}</a>
                            </v-list-item-title>
                        </v-list-item>
                    </v-list-item-group>
                </v-list>

            </v-navigation-drawer>
</template>

<script>
import axios
    from "axios";

export default {
    name: "UserMenu",
    data: () => ({
        userData: '',
        items: [
            { text: 'Заявки', icon: 'mdi-ticket', href: '/', target: '_self' },
            { text: 'ПО', icon: 'mdi-application-settings', href: '/software', target: '_self' },
            { text: 'Системы', icon: 'mdi-arrow-decision-outline', href: '/systems', target: '_self' },
            { text: 'Сервера', icon: 'mdi-desktop-classic', href: '/servers', target: '_self' },
            { text: 'Заявитель', icon: 'mdi-account-outline', href: '/applicants', target: '_self' },
            { text: 'Почт. сервера', icon: 'mdi-email-edit-outline', href: '/mail_servers', target: '_self' },
            { text: 'Помощь', icon: 'mdi-help-circle-outline', href: '/md', target: '_blank'},
        ],
    }),
    mounted() {
            axios.get("/user_data")
                .then((response) => {
                    this.$data.userData = response.data;
                });
    },
    methods: {
        linkToOtherWindow (url, target) {
            window.open(url, target);
        }
    },
    watch: {},
}

</script>

<style>


</style>
